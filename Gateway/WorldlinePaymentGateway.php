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

class WorldlinePaymentGateway extends AbstractPaymentGateway
{
    public const INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE = 'hosted_checkout_page';
    public const INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE = 'hosted_tokenization_page';

    public const AVAILABLE_INTEGRATION_METHODS = [
        self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE,
        self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE,
    ];

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

    protected function createOrder(Transaction $transaction): SdkDomain\Order
    {
        $amountOfMoney = new SdkDomain\AmountOfMoney();
        $amountOfMoney->setAmount($transaction->getAmount());
        $amountOfMoney->setCurrencyCode($transaction->getCurrencyCode());

        $customer = new SdkDomain\Customer();
        $customer->setMerchantCustomerId($transaction->getCustomerId());

        $orderReferences = new SdkDomain\OrderReferences();
        $orderReferences->setMerchantReference($transaction->getItemId());

        $order = new SdkDomain\Order();
        $order->setAmountOfMoney($amountOfMoney);
        $order->setCustomer($customer);
        $order->setReferences($orderReferences);

        return $order;
    }

    protected function callHostedCheckoutPage(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): SdkDomain\CreateHostedCheckoutResponse {
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        $createHostedCheckoutRequest = new SdkDomain\CreateHostedCheckoutRequest();
        $createHostedCheckoutRequest->setOrder($this->createOrder($transaction));

        $hostedCheckoutSpecificInput = new SdkDomain\HostedCheckoutSpecificInput();
        $hostedCheckoutSpecificInput->setReturnUrl($paymentGatewayConfiguration->get('return_url'));
        $hostedCheckoutSpecificInput->setLocale($paymentGatewayConfiguration->get('local'));
        $createHostedCheckoutRequest->setHostedCheckoutSpecificInput($hostedCheckoutSpecificInput);

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
        Transaction $transaction
    ): SdkDomain\CreateHostedTokenizationResponse {
        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        $createHostedTokenizationRequest = new SdkDomain\CreateHostedTokenizationRequest();
        $createHostedTokenizationRequest->setVariant("my-custom-template.html");

        return $merchantClient->hostedTokenization()->createHostedTokenization($createHostedTokenizationRequest);
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = []
    ): string {
        if (!in_array($paymentGatewayConfiguration->get('integration_method'), self::AVAILABLE_INTEGRATION_METHODS)) {
            throw new \UnexpectedValueException(sprintf(
                'The given \'integration_method\':%s is not valid (allowed values: %s)',
                $paymentGatewayConfiguration->get('integration_method'),
                json_encode(self::AVAILABLE_INTEGRATION_METHODS)
            ));
        }

        if (self::INTEGRATION_METHOD_HOSTED_CHECKOUT_PAGE === $paymentGatewayConfiguration->get('integration_method')) {
            $hostedCheckoutResponse = $this->callHostedCheckoutPage($paymentGatewayConfiguration, $transaction);

            return $this->templating->render('@IDCIPayment/Gateway/worldline/checkout.html.twig', [
                'hostedCheckoutResponse' => $hostedCheckoutResponse,
            ]);
        }

        if (self::INTEGRATION_METHOD_HOSTED_TOKENIZATION_PAGE === $paymentGatewayConfiguration->get('integration_method')) {
            $hostedTokenizationResponse = $this->callHostedTokenizationPage($paymentGatewayConfiguration, $transaction);

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
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->query->has('transaction_id')) {
            return new GatewayResponse();
        }

        $merchantClient = $this->createMerchantClient($paymentGatewayConfiguration);

        $hostedCheckoutResponse = $merchantClient->hostedCheckout()->getHostedCheckout($request->query->get('hostedCheckoutId'));

        //dd($hostedCheckoutResponse->getStatus());
        $gatewayResponse = (new GatewayResponse())
            ->setTransactionId($request->query->get('transaction_id'))
            ->setAmount($hostedCheckoutResponse->getCreatedPaymentOutput()->getPayment()->getPaymentOutput()->getAcquiredAmount()->getAmount() / 100)
            ->setCurrencyCode($hostedCheckoutResponse->getCreatedPaymentOutput()->getPayment()->getPaymentOutput()->getAcquiredAmount()->getCurrencyCode())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_APPROVED)
        ;

/*
        $request->query->get('RETURNMAC');
        $request->query->get('hostedCheckoutId');

        dd(
            $hostedCheckoutResponse->getCreatedPaymentOutput(),
            $hostedCheckoutResponse->getStatus(),
        );

        if ('PAYMENT_CREATED' === $hostedCheckoutResponse->getStatus())
*/

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
            ]
        );
    }
}
