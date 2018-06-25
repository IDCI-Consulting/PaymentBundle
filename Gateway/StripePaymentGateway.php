<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

class StripePaymentGateway extends AbstractPaymentGateway
{
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'publicKey' => $paymentGatewayConfiguration->get('public_key'),
            'transaction' => $transaction,
            'url' => $this->getCallbackURL($paymentGatewayConfiguration->getAlias()),
        ];
    }

    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        return $this->templating->render('@IDCIPaymentBundle/Resources/views/Gateway/stripe.html.twig', [
            'initializationData' => $initializationData,
        ]);
    }

    public function retrieveTransactionUuid(Request $request): ?string
    {
        if (!$request->request->has('transaction_id')) {
            throw new \InvalidArgumentException("The request do not contains 'transaction_id'");
        }

        return $request->get('transaction_id');
    }

    public function callback(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): ?Transaction {
        Stripe\Stripe::setApiKey($paymentGatewayConfiguration->get('secret_key'));

        Stripe\Charge::create([
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrencyCode(),
            'description' => 'Example charge',
            'source' => $request->get('stripeToken'),
        ]);

        $transaction->setStatus(Transaction::STATUS_VALIDATED);

        return $transaction;
    }

    public static function getParameterNames(): ?array
    {
        return [
            'public_key',
            'secret_key',
        ];
    }
}
