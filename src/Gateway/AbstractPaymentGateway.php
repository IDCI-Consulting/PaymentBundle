<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var Environment
     */
    protected $templating;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $dispatcher,
    ) {
        $this->templating = $templating;
        $this->dispatcher = $dispatcher;
    }

    abstract public function buildHTMLView(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = [],
    ): string;

    public function buildReturnHTMLView(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = [],
    ): ?string {
        return null;
    }

    abstract public function getReturnResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        Transaction $transaction,
        array $options = [],
    ): GatewayResponse;

    abstract public function getCallbackResponse(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
    ): GatewayResponse;

    public static function getParameterNames(): ?array
    {
        return [
            'callback_url',
            'return_url',
        ];
    }
}
