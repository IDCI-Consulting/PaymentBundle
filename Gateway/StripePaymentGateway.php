<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
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

    public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool {
        Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));

        $charge = Stripe\Charge::create([
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrencyCode(),
            'description' => 'Example charge',
            'source' => $request->get('stripeToken'),
        ]);

        return true;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }
}
