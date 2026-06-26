<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Model\Transaction;

class PaymentGatewayEvent
{
    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var PaymentGatewayConfigurationInterface
     */
    protected $paymentGatewayConfiguration;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * PaymentGatewayEvent constructor.
     */
    public function __construct(
        Transaction $transaction,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array &$options = []
    ) {
        $this->transaction = $transaction;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->options = &$options;
    }

    /**
     * Get transaction.
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    /**
     * Get paymentGatewayConfiguration.
     */
    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface
    {
        return $this->paymentGatewayConfiguration;
    }

    /**
     * Get options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set options.
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }
}
