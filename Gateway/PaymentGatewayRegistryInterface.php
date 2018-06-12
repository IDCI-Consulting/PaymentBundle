<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

interface PaymentGatewayRegistryInterface
{
    public function has(string $alias): bool;

    public function set(string $alias, PaymentGatewayInterface $paymentGateway): PaymentGatewayRegistryInterface;

    public function get(string $alias): PaymentGatewayInterface;

    public function getAll(): array;
}
