<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Gateway\StatusCode\PaymentStatusCode;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use PayPal\Api\Payment as PaypalPayment;
use PayPal\Api\PaymentExecution;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Symfony\Component\HttpFoundation\Request;

class PaypalPaymentGateway extends AbstractPaymentGateway
{
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'clientId' => $paymentGatewayConfiguration->get('client_id'),
            'transaction' => $transaction,
            'url' => $this->getCallbackURL($paymentGatewayConfiguration->getAlias()),
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paypal.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatusCode::STATUS_FAILED)
        ;

        $apiContext = new ApiContext(new OAuthTokenCredential(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret')
        ));

        $paypalPayment = PaypalPayment::get($request->get('paymentID'), $apiContext);

        $amount = $paypalPayment->getTransactions()[0]->getAmount();

        $gatewayResponse
            ->setTransactionUuid($request->get('transactionID'))
            ->setAmount($amount->total * 100)
            ->setCurrencyCode($amount->currency)
        ;

        $execution = new PaymentExecution();
        $execution->setPayerId($request->get('payerID'));

        $result = $paypalPayment->execute($execution, $apiContext);

        if ('approved' !== $result->getState()) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatusCode::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
        ];
    }
}
