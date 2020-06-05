<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripePaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    public function __construct(\Twig_Environment $templating, UrlGeneratorInterface $router)
    {
        parent::__construct($templating);

        $this->router = $router;
    }

    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'callbackUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'cancelUrl' => $paymentGatewayConfiguration->get('return_url'),
            'publicKey' => $paymentGatewayConfiguration->get('public_key'),
            'proxyUrl' => $this->router->generate(
                'idci_payment_stripepaymentgateway_proxy',
                ['configuration_alias' => $paymentGatewayConfiguration->getAlias()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'returnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'transaction' => $transaction,
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/stripe.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod('POST')) {
            throw new \UnexpectedValueException('Stripe : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        $gatewayResponse
            ->setTransactionUuid($request->get('transactionId'))
            ->setAmount($request->get('amount'))
            ->setCurrencyCode($request->get('currencyCode'))
        ;

        if (null !== $request->get('error')) {
            return $gatewayResponse->setMessage($request->get('error')['message']);
        }

        $gatewayResponse->setRaw($request->get('raw'));

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'public_key',
                'secret_key',
            ]
        );
    }
}
