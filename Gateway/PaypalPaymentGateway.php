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
    const PAYPAL_CHECKOUT_FLOW_TEMPLATE_MAPPING = [
        'PAY_NOW' => 'paypal_pay_now.html.twig',
        // 'SMART_BUTTON' => 'paypal_smart_button.html.twig',
    ];

    /**
     * {@inheritdoc}
     */
    public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array {
        return [
            'clientId' => $paymentGatewayConfiguration->get('client_id'),
            'transaction' => $transaction,
            'callbackUrl' => $paymentGatewayConfiguration->get('callback_url'),
            'returnUrl' => $paymentGatewayConfiguration->get('return_url'),
            'mode' => $paymentGatewayConfiguration->get('mode'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the payment gateway configuration use a non authorized checkout flow
     */
    public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string {
        $initializationData = $this->initialize($paymentGatewayConfiguration, $transaction);

        if (!array_key_exists($paymentGatewayConfiguration->get('checkout_flow'), self::PAYPAL_CHECKOUT_FLOW_TEMPLATE_MAPPING)) {
            throw new \UnexpectedValueException(
                sprintf(
                    'The checkout flow "%s" is not yet implemented in %s',
                    $paymentGatewayConfiguration->get('checkout_flow'),
                    self::class
                )
            );
        }

        return $this->templating->render(
            sprintf(
                '@IDCIPayment/Gateway/%s',
                self::PAYPAL_CHECKOUT_FLOW_TEMPLATE_MAPPING[$paymentGatewayConfiguration->get('checkout_flow')]
            ),
            [
                'initializationData' => $initializationData,
            ]
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \UnexpectedValueException If the request method is not POST
     */
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

        $apiContext->setConfig([
            'mode' => $paymentGatewayConfiguration->get('mode'),
        ]);

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

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return array_merge(
            parent::getParameterNames(),
            [
                'client_id',
                'client_secret',
                'mode',
                'checkout_flow',
            ]
        );
    }
}
