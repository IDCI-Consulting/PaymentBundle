<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use OnlinePayments\Sdk\Authentication\V1HmacAuthenticator;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\Domain as SdkDomain;
use OnlinePayments\Sdk\Merchant\MerchantClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class WorldlinePaymentGateway extends AbstractPaymentGateway
{
    public const INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE = 'hosted_checkout_page';
    public const INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE = 'hosted_tokenization_page';

    public const AVAILABLE_INTEGRATION_METHODS = [
        self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE,
        self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE,
    ];

    public const PAYMENT_STATUS_CREATED = 'CREATED';
    public const PAYMENT_STATUS_CANCELLED = 'CANCELLED';
    public const PAYMENT_STATUS_REJECTED = 'REJECTED';
    public const PAYMENT_STATUS_REJECTED_CAPTURE = 'REJECTED_CAPTURE';
    public const PAYMENT_STATUS_REDIRECTED = 'REDIRECTED';
    public const PAYMENT_STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const PAYMENT_STATUS_PENDING_COMPLETION = 'PENDING_COMPLETION';
    public const PAYMENT_STATUS_PENDING_CAPTURE = 'PENDING_CAPTURE';
    public const PAYMENT_STATUS_AUTHORIZATION_REQUESTED = 'AUTHORIZATION_REQUESTED';
    public const PAYMENT_STATUS_CAPTURE_REQUESTED = 'CAPTURE_REQUESTED';
    public const PAYMENT_STATUS_CAPTURED = 'CAPTURED';
    public const PAYMENT_STATUS_REVERSED = 'REVERSED';
    public const PAYMENT_STATUS_REFUND_REQUESTED = 'REFUND_REQUESTED';
    public const PAYMENT_STATUS_REFUNDED = 'REFUNDED';

    public const PAYMENT_STATUS_MAP = [
        self::PAYMENT_STATUS_CREATED => PaymentStatus::STATUS_CREATED,
        self::PAYMENT_STATUS_CANCELLED => PaymentStatus::STATUS_CANCELED,
        self::PAYMENT_STATUS_REJECTED => PaymentStatus::STATUS_FAILED,
        self::PAYMENT_STATUS_REJECTED_CAPTURE => PaymentStatus::STATUS_FAILED,
        self::PAYMENT_STATUS_REDIRECTED => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_PENDING_PAYMENT => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_PENDING_COMPLETION => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_PENDING_CAPTURE => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_AUTHORIZATION_REQUESTED => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_CAPTURE_REQUESTED => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_CAPTURED => PaymentStatus::STATUS_APPROVED,
        self::PAYMENT_STATUS_REVERSED => PaymentStatus::STATUS_CANCELED,
        self::PAYMENT_STATUS_REFUND_REQUESTED => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_REFUND_REQUESTED => PaymentStatus::STATUS_PENDING,
        self::PAYMENT_STATUS_REFUNDED => PaymentStatus::STATUS_CANCELED,
    ];

    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router
    ) {
        parent::__construct($templating, $dispatcher);

        $this->router = $router;
    }

    private static function mapPaymentStatus(string $status): string
    {
        if (!isset(self::PAYMENT_STATUS_MAP[$status])) {
            throw new \UnexpectedValueException(sprintf('Undefined payment status: %s', $status));
        }

        return self::PAYMENT_STATUS_MAP[$status];
    }

    protected function createMerchantClient(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration): MerchantClientInterface
    {
        $communicatorConfiguration = new CommunicatorConfiguration(
            $paymentGatewayConfiguration->get('api_key'),
            $paymentGatewayConfiguration->get('api_secret'),
            $paymentGatewayConfiguration->get('api_endpoint'),
            $paymentGatewayConfiguration->get('integrator'),
            null
        );

        $authenticator = new V1HmacAuthenticator($communicatorConfiguration);
        $communicator = new Communicator($communicatorConfiguration, $authenticator);
        $client = new Client($communicator);

        return $client->merchant($paymentGatewayConfiguration->get('merchant_id'));
    }

    protected function createOrder(Transaction $transaction, array $paymentGatewayOptions): SdkDomain\Order
    {
        $amountOfMoney = new SdkDomain\AmountOfMoney();
        $amountOfMoney->setAmount($transaction->getAmount());
        $amountOfMoney->setCurrencyCode($transaction->getCurrencyCode());

        $customer = new SdkDomain\Customer();
        $customer->setMerchantCustomerId($transaction->getCustomerId());

        $orderReferences = new SdkDomain\OrderReferences();
        //$orderReferences->setMerchantReference($paymentGatewayOptions['merchant_reference'] ?? $transaction->getId());
        $orderReferences->setMerchantReference($transaction->getId());
        //$orderReferences->setOperationGroupReference($transaction->getId());

        $order = new SdkDomain\Order();
        $order->setAmountOfMoney($amountOfMoney);
        $order->setCustomer($customer);
        $order->setReferences($orderReferences);

        return $order;
    }

    protected function callHostedCheckoutPage(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $paymentGatewayOptions
    ): SdkDomain\CreateHostedCheckoutResponse {
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        $createHostedCheckoutRequest = new SdkDomain\CreateHostedCheckoutRequest();
        $createHostedCheckoutRequest->setOrder($this->createOrder($transaction, $paymentGatewayOptions));

        $hostedCheckoutSpecificInput = new SdkDomain\HostedCheckoutSpecificInput();
        $hostedCheckoutSpecificInput->setReturnUrl($paymentGatewayConfiguration->get('return_url'));

        if (null !== $paymentGatewayOptions['locale']) {
            $hostedCheckoutSpecificInput->setLocale($paymentGatewayOptions['locale']);
        }

        $createHostedCheckoutRequest->setHostedCheckoutSpecificInput($hostedCheckoutSpecificInput);

        $feedbacks = new SdkDomain\Feedbacks();
        $feedbacks->setWebhooksUrls([$paymentGatewayConfiguration->get('callback_url')]);
        $createHostedCheckoutRequest->setFeedbacks($feedbacks);

        $hostedCheckoutResponse = $merchantClient->hostedCheckout()->createHostedCheckout($createHostedCheckoutRequest);

        $transaction
            ->setStatus(PaymentStatus::STATUS_PENDING)
            ->addMetadata('hosted_checkout_id', $hostedCheckoutResponse->getHostedCheckoutId())
            ->addMetadata('return_mac', $hostedCheckoutResponse->getReturnMac())
            ->addMetadata('redirect_url', $hostedCheckoutResponse->getRedirectUrl())
        ;
        $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return $hostedCheckoutResponse;
    }

    protected function callHostedTokenizationPage(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $paymentGatewayOptions
    ): SdkDomain\CreateHostedTokenizationResponse {
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        $createHostedTokenizationRequest = new SdkDomain\CreateHostedTokenizationRequest();
        if (null !== $paymentGatewayOptions['template_file']) {
            $createHostedTokenizationRequest->setVariant($paymentGatewayOptions['template_file']);
        }

        $hostedTokenizationResponse = $merchantClient->hostedTokenization()->createHostedTokenization($createHostedTokenizationRequest);

        $transaction
            ->setStatus(PaymentStatus::STATUS_PENDING)
            ->addMetadata('hosted_tokenization_id', $hostedTokenizationResponse->getHostedTokenizationId())
            ->addMetadata('hosted_tokenization_url', $hostedTokenizationResponse->getHostedTokenizationUrl())
        ;
        $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return $hostedTokenizationResponse;
    }

    protected function resolveGatewayOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $this->configureGatewayOptions($resolver);

        return $resolver->resolve($options);
    }

    protected function configureGatewayOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('merchant_reference', null)->setAllowedTypes('merchant_reference', ['null', 'string'])
            ->setDefault('locale', null)->setAllowedTypes('locale', ['null', 'string'])
            ->setDefault('template_file', null)->setAllowedTypes('template_file', ['null', 'string'])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): string {
        $paymentGatewayOptions = $this->resolveGatewayOptions($options);

        if (!in_array($paymentGatewayConfiguration->get('integration_method'), self::AVAILABLE_INTEGRATION_METHODS)) {
            throw new \UnexpectedValueException(sprintf(
                'The given \'integration_method\':%s is not valid (allowed values: %s)',
                $paymentGatewayConfiguration->get('integration_method'),
                json_encode(self::AVAILABLE_INTEGRATION_METHODS)
            ));
        }

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $paymentGatewayConfiguration->get('integration_method')) {
            $hostedCheckoutResponse = $this->callHostedCheckoutPage($paymentGatewayConfiguration, $transaction, $paymentGatewayOptions);

            return $this->templating->render('@IDCIPayment/Gateway/worldline/checkout.html.twig', [
                'hosted_checkout_response' => $hostedCheckoutResponse,
            ]);
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $paymentGatewayConfiguration->get('integration_method')) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($paymentGatewayConfiguration, $transaction, $paymentGatewayOptions);

            return $this->templating->render('@IDCIPayment/Gateway/worldline/tokenization.html.twig', [
                'hosted_tokenization_response' => $hostedTokenizationResponse,
                'wordline_host' => $paymentGatewayConfiguration->get('api_endpoint'),
                'transaction' => $transaction,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): GatewayResponse {
        $paymentGatewayOptions = $this->resolveGatewayOptions($options);

        if ($request->query->has('hosted_tokenization_id') && PaymentStatus::STATUS_PENDING === $transaction->getStatus()) {
            $createPaymentResponse = $this->sendCreatePaymentRequest($request, $paymentGatewayConfiguration, $transaction, $paymentGatewayOptions);

            $gatewayResponse = (new GatewayResponse())
                ->setTransactionId($transaction->getId())
                ->setAmount($createPaymentResponse->getPayment()->getPaymentOutput()->getAcquiredAmount()->getAmount())
                ->setCurrencyCode($createPaymentResponse->getPayment()->getPaymentOutput()->getAcquiredAmount()->getCurrencyCode())
                ->setDate(new \DateTime())
                ->setStatus(self::mapPaymentStatus($createPaymentResponse->getPayment()->getStatus()))
            ;

            return $gatewayResponse;
        }

        if (!$request->query->has('transaction_id')) {
            return new GatewayResponse();
        }

        if (
            $request->query->get('RETURNMAC') !== $transaction->getMetadata('return_mac')
            || $request->query->get('hostedCheckoutId') !== $transaction->getMetadata('hosted_checkout_id')
        ) {
            return new GatewayResponse();
        }

        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);
        $hostedCheckoutResponse = $merchantClient->hostedCheckout()->getHostedCheckout($request->query->get('hostedCheckoutId'));

        $gatewayResponse = (new GatewayResponse())
            ->setTransactionId($transaction->getId())
            ->setAmount($hostedCheckoutResponse->getCreatedPaymentOutput()->getPayment()->getPaymentOutput()->getAcquiredAmount()->getAmount())
            ->setCurrencyCode($hostedCheckoutResponse->getCreatedPaymentOutput()->getPayment()->getPaymentOutput()->getAcquiredAmount()->getCurrencyCode())
            ->setDate(new \DateTime())
            ->setStatus(self::mapPaymentStatus($hostedCheckoutResponse->getCreatedPaymentOutput()->getPayment()->getStatus()))
            // Useless ?
            ->setRaw([
                'return_mac' => $request->query->get('RETURNMAC'),
                'hosted_checkout_id' => $request->query->get('hostedCheckoutId'),
                'hosted_checkout_response' => $hostedCheckoutResponse,
            ])
        ;

        return $gatewayResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return $this->processNotificationMessage($request, $paymentGatewayConfiguration);
    }

    public function sendCreatePaymentRequest(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $paymentGatewayOptions
    ) {
        $createPaymentRequest = new SdkDomain\CreatePaymentRequest();
        $createPaymentRequest->setHostedTokenizationId($request->query->get('hosted_tokenization_id'));

        $redirectionData = new SdkDomain\RedirectionData();
        $redirectionData->setReturnUrl("https://yourRedirectionUrl.com");

        $threeDSecure = new SdkDomain\ThreeDSecure();
        $threeDSecure->setRedirectionData($redirectionData);
        $threeDSecure->setSkipAuthentication(false);

        $cardPaymentMethodSpecificInput = new SdkDomain\CardPaymentMethodSpecificInput();
        $cardPaymentMethodSpecificInput->setThreeDSecure($threeDSecure);

        $createPaymentRequest->setCardPaymentMethodSpecificInput($cardPaymentMethodSpecificInput);

        $order = new SdkDomain\Order();

        $orderReferences = new SdkDomain\OrderReferences();
        //$orderReferences->setMerchantReference($paymentGatewayOptions['merchant_reference'] ?? $transaction->getId());
        $orderReferences->setMerchantReference($transaction->getId());
        $order->setReferences($orderReferences);

        $browserData = new SdkDomain\BrowserData();
        $browserData->setColorDepth(24);
        $browserData->setJavaScriptEnabled(false);
        $browserData->setScreenHeight("1080");
        $browserData->setScreenWidth("1920");

        $customerDevice = new SdkDomain\CustomerDevice();
        $customerDevice->setAcceptHeader(
            "text/html,application/xhtml+xml,application/xmlq=0.9,image/webp,image/apng,*/*q=0.8,application/signed-exchangev=b3"
        );

        $customerDevice->setLocale($paymentGatewayOptions['locale']);
        //$customerDevice->setTimezoneOffsetUtcMinutes("-180");
        $customerDevice->setUserAgent(
            "Mozilla/5.0 (Windows NT 10.0 Win64 x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36"
        );
        $customerDevice->setBrowserData($browserData);

        $customer = new SdkDomain\Customer();
        $customer->setMerchantCustomerId($transaction->getCustomerId());
        $customer->setDevice($customerDevice);
        $order->setCustomer($customer);

        $amountOfMoney = new SdkDomain\AmountOfMoney();
        $amountOfMoney->setAmount($transaction->getAmount());
        $amountOfMoney->setCurrencyCode($transaction->getCurrencyCode());
        $order->setAmountOfMoney($amountOfMoney);

        $createPaymentRequest->setOrder($order);

        $createPaymentResponse = $this->createMerchantClient($paymentGatewayConfiguration)->payments()->createPayment($createPaymentRequest);

        $transaction
            ->setRaw([
                'hosted_tokenization_create_payment_request' => $createPaymentRequest,
                'hosted_tokenization_create_payment_response' => $createPaymentResponse,
            ])
        ;

        $this->dispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return $createPaymentResponse;
    }

    public function processNotificationMessage(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ) {
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);
        $rawData = json_decode($request->getContent(), true);

        $gatewayResponse = (new GatewayResponse())
            //->setTransactionId($rawData['payment']['paymentOutput']['references']['operationGroupReference'])
            ->setTransactionId($rawData['payment']['paymentOutput']['references']['merchantReference'])
            ->setAmount($rawData['payment']['paymentOutput']['acquiredAmount']['amount'])
            ->setCurrencyCode($rawData['payment']['paymentOutput']['acquiredAmount']['currencyCode'])
            ->setDate(new \DateTime())
            ->setStatus(self::mapPaymentStatus($rawData['payment']['status']))
            ->setRaw([
                'webhook_request_content' => $rawData,
            ])
        ;

        // TODO: TransactionNotificationHistory
        // Capture payment if configured to do it
        if (self::PAYMENT_STATUS_PENDING_CAPTURE === $rawData['payment']['status']) {
            $capturePaymentRequest = new SdkDomain\CapturePaymentRequest();
            //$capturePaymentRequest->setAmount($rawData['payment']['paymentOutput']['acquiredAmount']['amount']);
            $capturePaymentRequest->setAmount($gatewayResponse->getAmount());
            $capturePaymentRequest->setIsFinal(true);

            /*
            $paymentReferences = new SdkDomain\PaymentReferences();
            //$paymentReferences->setOperationGroupReference($rawData['payment']['paymentOutput']['references']['operationGroupReference']);
            $paymentReferences->setOperationGroupReference($gatewayResponse->getTransactionId());
            $capturePaymentRequest->setReferences($paymentReferences);
            */

            try {
                $captureResponse = $merchantClient->payments()->capturePayment($rawData['payment']['id'], $capturePaymentRequest);
            } catch (\Exception $e) {
                // TODO: Use TransactionNotificationMessage (Status ERROR)
                $captureResponse = $e->getMessage();
            }

            $gatewayResponse = (new GatewayResponse())
                //->setTransactionId($captureResponse->getCaptureOutput()->getReferences()->getOperationGroupReference())
                ->setTransactionId($captureResponse->getCaptureOutput()->getReferences()->getMerchantReference())
                ->setAmount($captureResponse->getCaptureOutput()->getAcquiredAmount()->getAmount())
                ->setCurrencyCode($captureResponse->getCaptureOutput()->getAcquiredAmount()->getCurrencyCode())
                ->setDate(new \DateTime())
                ->setStatus(self::mapPaymentStatus($captureResponse->getCaptureOutput()->getStatus()))
                ->setRaw([
                    'capture_response' => $captureResponse,
                ])
            ;
        }

        return $gatewayResponse;
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'api_endpoint',
                'api_key',
                'api_secret',
                'merchant_id',
                'integrator',
                'integration_method',
            ]
        );
    }
}
