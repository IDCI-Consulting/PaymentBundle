<?php

namespace IDCI\Bundle\PaymentBundle\System;

class PaymentSystemRegistry
{
    private array $paymentSystems = [];

    public function has(string $alias): bool
    {
        return isset($this->paymentSystems[$alias]);
    }

    public function set(string $alias, PaymentSystemInterface $paymentSystem): self
    {
        $this->paymentSystems[$alias] = $paymentSystem;

        return $this;
    }

    public function get(string $alias): PaymentSystemInterface
    {
        if (!isset($this->paymentSystems[$alias])) {
            throw new \InvalidArgumentException(sprintf('Undefined payment system "%s"', $alias));
        }

        return $this->paymentSystems[$alias];
    }

    public function getAll(): array
    {
        return $this->paymentSystems;
    }
}
