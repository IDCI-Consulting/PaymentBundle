<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payplug;
use Symfony\Component\HttpFoundation\Request;

class PayPlugPaymentGateway extends AbstractPaymentGateway
{
    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        Payplug\Payplug::setSecretKey($paymentGatewayConfiguration->get('secret_key'));

        $payment = Payplug\Payment::create([
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrencyCode(),
            'customer' => [
                'email' => null,
                'first_name' => null,
                'last_name' => null,
            ],
            'hosted_payment' => [
                'return_url' => $paymentGatewayConfiguration->get('return_url'),
                'cancel_url' => $paymentGatewayConfiguration->get('return_url'),
            ],
            'notification_url' => $paymentGatewayConfiguration->get('callback_url'),
            'metadata' => [
                'transaction_id' => $transaction->getId(),
            ],
        ]);

        return [
            'payment' => $payment,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPayment/Gateway/payplug.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        return new GatewayResponse();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the request method is not POST
     */
    public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        if (!$request->isMethod(Request::METHOD_POST)) {
            throw new \UnexpectedValueException('PayPlug : Payment Gateway error (Request method should be POST)');
        }

        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (empty($request->request->all())) {
            return $gatewayResponse->setMessage('The request do not contains required post data');
        }
        $params = $request->request->all();

        $gatewayResponse
            ->setTransactionUuid($params['metadata']['transaction_id'])
            ->setAmount($params['amount'])
            ->setCurrencyCode($params['currency'])
        ;

        if (!$params['is_paid']) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'secret_key',
            ]
        );
    }
}
