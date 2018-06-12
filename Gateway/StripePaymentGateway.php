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
        $publicKey = $this->paymentGatewayConfiguration->getParameters()['public_key'];
        $amount = $payment->getAmount();

        $view = <<<EOT
        <form action="http://jarvis.inflexyon.docker/payment/process" method="POST">
          <script
            src="https://checkout.stripe.com/checkout.js" class="stripe-button"
            data-key="$publicKey"
            data-amount="$amount"
            data-name="Stripe.com"
            data-description="Example charge"
            data-image="https://stripe.com/img/documentation/checkout/marketplace.png"
            data-locale="auto"
            data-zip-code="true">
          </script>
        </form>
EOT;

        return $view;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }
}
