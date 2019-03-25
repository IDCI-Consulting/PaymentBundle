<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Exception\UnexpectedPaymentGatewayException;

class PaymentGatewayRegistry implements PaymentGatewayRegistryInterface
{
    /**
     * @var array
     */
    private $paymentGateways;

    public function has(string $alias): bool
    {
        return isset($this->paymentGateways[$alias]);
    }

    public function set(string $alias, PaymentGatewayInterface $paymentGateway): PaymentGatewayRegistryInterface
    {
        $this->paymentGateways[$alias] = $paymentGateway;

        return $this;
    }

    public function get(string $alias): PaymentGatewayInterface
    {
        if (!is_string($alias)) {
            throw new UnexpectedPaymentGatewayException($alias, 'string');
        }

        if (!isset($this->paymentGateways[$alias])) {
            throw new \InvalidArgumentException(sprintf('could not load payment gateway %s', $alias));
        }

        return $this->paymentGateways[$alias];
    }

    public function getAll(): array
    {
        return $this->paymentGateways;
    }
}
