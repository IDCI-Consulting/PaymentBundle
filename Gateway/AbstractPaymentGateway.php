<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $templating;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function __construct(
        \Twig_Environment $templating,
        EventDispatcherInterface $dispatcher
    ) {
        $this->templating = $templating;
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function initialize(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): array;

    /**
     * {@inheritdoc}
     */
    abstract public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction
    ): string;

    /**
     * {@inheritdoc}
     */
    abstract public function getResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration
    ): GatewayResponse;

    /**
     * {@inheritdoc}
     */
    public static function getParameterNames(): ?array
    {
        return [
            'callback_url',
            'return_url',
        ];
    }
}
