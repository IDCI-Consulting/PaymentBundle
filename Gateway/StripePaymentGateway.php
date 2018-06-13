<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentGateway extends AbstractPaymentGateway
{
    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string
    {
        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/stripe.html.twig', [
            'publicKey' => $paymentGatewayConfiguration->get('public_key'),
            'payment' => $payment,
        ]);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }

    /**
     * METHODS ONLY USED FOR TESTS.
     */
    public function preProcess(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ) {
        Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));
    }

    public function postProcess(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ) {
        $charge = \Stripe\Charge::create([
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrencyCode(),
            'description' => 'Example charge',
            'source' => $request->get('stripeToken'),
        ]);
    }
}
