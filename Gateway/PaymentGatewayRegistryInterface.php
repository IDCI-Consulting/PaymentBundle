<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

interface PaymentGatewayRegistryInterface
{
    /**
     * Check if the payment gateway alias exist in the registry.
     *
     * @method has
     *
     * @param string $alias
     *
     * @return bool
     */
    public function has(string $alias): bool;

    /**
     * Add the payment gateway in the registry by its alias.
     *
     * @method set
     *
     * @param string                  $alias
     * @param PaymentGatewayInterface $paymentGateway
     *
     * @return PaymentGatewayRegistryInterface
     */
    public function set(string $alias, PaymentGatewayInterface $paymentGateway): PaymentGatewayRegistryInterface;

    /**
     * Retrieve the payment gateway from the registry by its alias.
     *
     * @method get
     *
     * @param string $alias
     *
     * @return PaymentGatewayInterface
     */
    public function get(string $alias): PaymentGatewayInterface;

    /**
     * Retrieve all the payment gateways from the registry.
     *
     * @method getAll
     *
     * @return array<PaymentGatewayInterface>
     */
    public function getAll(): array;
}
