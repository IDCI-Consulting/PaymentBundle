<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Transaction as EntityTransaction;
use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Payplug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayPlugPaymentGateway extends AbstractPaymentGateway
{
    /**
     * @var ObjectManager
     */
    private $om;

    /*
    * TEMPORARY PLACEHOLDER TO INJECT OBJECT MANAGER
    */
    public function __construct(
        \Twig_Environment $templating,
        UrlGeneratorInterface $router,
        ObjectManager $om
    ) {
        parent::__construct($templating, $router);

        $this->om = $om;
    }

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

        $transaction->addMetadata('payplug_payment_id', $payment->id);
        $this->om->flush();

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

        if (!$request->query->has('transaction_id')) {
            return $gatewayResponse->setMessage('The request do not contains "transaction_id"');
        }

        $gatewayResponse->setTransactionUuid($request->get('transaction_id'));

        // OBJECT MANAGER SHOULD NOT BE USED HERE
        $transaction = $this->om->getRepository(EntityTransaction::class)->findOneBy(['id' => $gatewayResponse->getTransactionUuid()]);
        Payplug\Payplug::setSecretKey($paymentGatewayConfiguration->get('secret_key'));
        $payment = Payplug\Payment::retrieve($transaction->getMetadata('payplug_payment_id'));
        // USED TO RETRIVE PAY PLUG PAYMENT ID FROM TRANSACTION METADATA

        $gatewayResponse->setAmount($payment->amount)->setCurrencyCode($payment->currency);

        if (!$payment->is_paid) {
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
