<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

interface PaymentGatewayConfigurationInterface
{
    public function get(string $key);

    public function set(string $key, $value): PaymentGatewayConfigurationInterface;
}
