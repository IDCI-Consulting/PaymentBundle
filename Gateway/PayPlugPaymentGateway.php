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
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        $callbackUrl = $this->getCallbackURL($paymentGatewayConfiguration->getAlias(), [
            'transaction_id' => $transaction->getId(),
        ]);

        Payplug\Payplug::setSecretKey($paymentGatewayConfiguration->get('secret_key'));

        $payment = Payplug\Payment::create(array(
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrencyCode(),
            'customer' => array(
                'email' => null,
                'first_name' => null,
                'last_name' => null,
            ),
            'hosted_payment' => array(
                'return_url' => $callbackUrl,
                'cancel_url' => $callbackUrl,
            ),
        ));

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
        dump($request);
        die();
        $gatewayResponse = (new GatewayResponse())
            ->setDate(new \DateTime())
            ->setStatus(PaymentStatus::STATUS_FAILED)
        ;

        $gatewayResponse->setTransactionUuid($request->get('transaction_id'));

        return $gatewayResponse->setStatus(PaymentStatus::STATUS_APPROVED);
    }

    public static function getParameterNames(): ?array
    {
        return [
            'secret_key',
        ];
    }
}
