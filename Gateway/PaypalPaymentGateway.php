<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

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

    public function buildHTMLView(PaymentGatewayConfigurationInterface $paymentGatewayConfiguration, Transaction $transaction): string
    {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paypal.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        if (!$request->request->has('transactionID')) {
            throw new \InvalidArgumentException("The request do not contains 'transactionID'");
        }

        return $request->get('transactionID');
    }

    public function callback(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?Transaction {
        $transaction->setStatus(Transaction::STATUS_FAILED);

        $apiContext = new ApiContext(new OAuthTokenCredential(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret')
        ));

        $paypalPayment = PaypalPayment::get($request->get('paymentID'), $apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId($request->get('payerID'));

        $result = $paypalPayment->execute($execution, $apiContext);

        return $transaction->setStatus(Transaction::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'client_id',
            'client_secret',
        ];
    }
}
