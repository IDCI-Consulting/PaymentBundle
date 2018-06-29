<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payplug;
use Payplug\Resource\Payment;
use Symfony\Component\HttpFoundation\Request;

class PayPlugPaymentGateway extends AbstractPaymentGateway
{
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackUrl = $this->getCallbackURL($paymentGatewayConfiguration->getAlias());
        $returnUrl = $this->getReturnURL($paymentGatewayConfiguration->getAlias(), [
            'transaction_id' => $transaction->getId(),
        ]);

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
                'return_url' => $returnUrl,
                'cancel_url' => $returnUrl,
            ],
            'notification_url' => $callbackUrl,
            'metadata' => [
                'transaction_id' => $transaction->getId(),
            ],
        ]);

        return [
            'payment' => $payment,
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/payplug.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse {
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        if (null !== $request->getContent()) {
            return $gatewayResponse->setMessage('The request do not contains required post data');
        }

        $requestContent = $request->getContent();

        try {
            $resource = \Payplug\Notification::treat($requestContent);
        } catch (\Payplug\Exception\PayplugException $exception) {
            return $gatewayResponse->setMessage('Treat transaction is impossible');
        }

        $params = json_decode($requestContent);

        $gatewayResponse
            ->setTransactionUuid($params['metadata']['transaction_id'])
            ->setAmount($params['amount'])
            ->setCurrencyCode($params['currency'])
        ;

        if ($resource instanceof Payment && $resource->is_paid) {
            return $gatewayResponse->setMessage('Transaction unauthorized');
        }

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'secret_key',
        ];
    }
}
