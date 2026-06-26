<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

class PaymentGatewayRegistry implements PaymentGatewayRegistryInterface
{
    /**
     * @var array
     */
    private $paymentGateways;

    /**
     * {@inheritdoc}
     */
    public function has(string $alias): bool
    {
        return isset($this->paymentGateways[$alias]);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $alias, PaymentGatewayInterface $paymentGateway): PaymentGatewayRegistryInterface
    {
        $this->paymentGateways[$alias] = $paymentGateway;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException If the payment gateway alias doesn't exists
     */
    public function get(string $alias): PaymentGatewayInterface
    {
        if (!isset($this->paymentGateways[$alias])) {
            throw new \InvalidArgumentException(sprintf('could not load payment gateway %s', $alias));
        }

        return $this->paymentGateways[$alias];
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->paymentGateways;
    }
}
