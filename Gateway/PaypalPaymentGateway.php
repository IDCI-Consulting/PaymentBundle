<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use PayPal\Api\Payment as PaypalPayment;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Symfony\Component\HttpFoundation\Request;

class PaypalPaymentGateway extends AbstractPaymentGateway
{
    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Payment $payment): string
    {
        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paypal.html.twig', [
            'clientId' => $paymentGatewayConfiguration->get('client_id'),
            'payment' => $payment,
        ]);
    }

    public function executePayment(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ): ?bool {
        $apiContext = new ApiContext(new OAuthTokenCredential(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret')
        ));

        $paypalPayment = PaypalPayment::get($request->get('paymentID'), $apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId($request->get('payerID'));

        $result = $paypalPayment->execute($execution, $apiContext);

        return true;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
        ];
    }
}
