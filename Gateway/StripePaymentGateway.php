<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentGateway extends AbstractPaymentGateway
{
    public function preProcess(Request $request)
    {
        Stripe\Stripe::setApiKey($this->paymentGatewayConfiguration->getParameters()['secret_key']);
    }

    public function postProcess(Request $request)
    {
        $charge = \Stripe\Charge::create([
            'amount' => $this->payment->getAmount(),
            'currency' => $this->payment->getCurrencyCode(),
            'description' => 'Example charge',
            'source' => $request->get('stripeToken'),
        ]);
    }

    public function buildHTMLView(Payment $payment): string
    {
        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/stripe.html.twig', [
            'publicKey' => $this->paymentGatewayConfiguration->getParameters()['public_key'],
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
}
