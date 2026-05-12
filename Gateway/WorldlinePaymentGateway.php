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
use Symfony\Component\OptionsResolver\Options;
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
        $orderReferences->setMerchantReference($paymentGatewayOptions['merchant_reference'] ?? $transaction->getItemId());

        $order = new SdkDomain\Order();
        $order->setAmountOfMoney($amountOfMoney);
        $order->setCustomer($customer);
        $order->setReferences($orderReferences);

        return $order;
    }

    protected function createFeedbacks(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration): SdkDomain\Feedbacks
    {
        $feedbacks = new SdkDomain\Feedbacks();
        $feedbacks->setWebhooksUrls([
            sprintf('%s:%d%s',
                'http://payment-gateway-lyon.idci.fr',
                8155,
                $this->router->generate(
                    'idci_payment_payment_gateway_callback',
                    [
                        'configuration_alias' => $paymentGatewayConfiguration->getAlias(),
                    ],
                    UrlGeneratorInterface::ABSOLUTE_PATH
                )
            ),
        ]);

        return $feedbacks;
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

        $createHostedCheckoutRequest->setFeedbacks($this->createFeedbacks($paymentGatewayConfiguration));

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
        $createHostedTokenizationRequest->setVariant("my-custom-template.html");

        return $merchantClient->hostedTokenization()->createHostedTokenization($createHostedTokenizationRequest);
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
                'hostedCheckoutResponse' => $hostedCheckoutResponse,
            ]);
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $paymentGatewayConfiguration->get('integration_method')) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($paymentGatewayConfiguration, $transaction, $paymentGatewayOptions);

            return $this->templating->render('@IDCIPayment/Gateway/worldline/tokenization.html.twig', [
                'hostedTokenizationResponse' => $hostedTokenizationResponse,
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): GatewayResponse {
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
            ->setRaw([
                'return_mac' => $request->query->get('RETURNMAC'),
                'hosted_checkout_id' => $request->query->get('hostedCheckoutId'),
                'hosted_checkout_responses' => [
                    (new \DateTime('now'))->format('Y-m-d h:i:s') => $hostedCheckoutResponse,
                ],
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
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        // Capture payment if configured to do it
        dd('getCallbackResponse', $merchantClient);
        return new GatewayResponse();
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
