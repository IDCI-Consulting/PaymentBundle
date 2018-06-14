<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
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

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
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
        try {
            $apiContext = new ApiContext(new OAuthTokenCredential(
                $paymentGatewayConfiguration->get('client_id'),
                $paymentGatewayConfiguration->get('client_secret')
            ));

            $payment = PaypalPayment::get($request->get('paymentID'), $apiContext);

            $execution = new PaymentExecution();
            $execution->setPayerId($request->get('payerID'));

            $result = $payment->execute($execution, $apiContext);
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    public function postProcess(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Payment $payment
    ) {
        return;
    }
}