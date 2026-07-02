<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

interface PaymentGatewayRegistryInterface
{
    /**
     * Check if the payment gateway alias exist in the registry.
     *
     * @method has
     */
    public function has(string $alias): bool;

    /**
     * Add the payment gateway in the registry by its alias.
     *
     * @method set
     */
    public function set(string $alias, PaymentGatewayInterface $paymentGateway): PaymentGatewayRegistryInterface;

    /**
     * Retrieve the payment gateway from the registry by its alias.
     *
     * @method get
     */
    public function get(string $alias): PaymentGatewayInterface;

    /**
     * Retrieve all the payment gateways from the registry.
     *
     * @method getAll
     */
    public function getAll(): array;
}
