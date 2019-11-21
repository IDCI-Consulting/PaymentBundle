<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
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
        return array(
            'clientId' => $paymentGatewayConfiguration->get('client_id'),
            'transaction' => $transaction,
            'callbackUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'returnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'environment' => $paymentGatewayConfiguration->get('environment'),
        );
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/paypal.html.twig', array(
            'initializationData' => $initializationData,
        ));
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod(Request::METHOD_POST)) {
            throw new \UnexpectedValueException('Paypal : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setRaw($request->request->all())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        $apiContext = new ApiContext(new OAuthTokenCredential(
            $paymentGatewayConfiguration->get('client_id'),
            $paymentGatewayConfiguration->get('client_secret')
        ));

        $paypalPayment = PaypalPayment::get($request->get('paymentID'), $apiContext);

        $amount = $paypalPayment->getTransactions()[0]->getAmount();

        $gatewayResponse
            ->setPaymentMethod($paypalPayment->getPayer()->getPaymentMethod())
            ->setTransactionUuid($request->get('transactionID'))
            ->setAmount($amount->total * 100)
            ->setCurrencyCode($amount->currency)
            ->setRaw($paypalPayment->toArray())
        ;

        $execution = new PaymentExecution();
        $execution->setPayerId($request->get('payerID'));

        $result = $paypalPayment->execute($execution, $apiContext);

        if ('approved' !== $result->getState()) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            array(
                'client_id',
                'client_secret',
                'environment',
            )
        );
    }
}
