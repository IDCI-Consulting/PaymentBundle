<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class NoPaymentGatewayConfigurationFoundException extends \InvalidArgumentException
{
    public function __construct(string $alias)
    {
        parent::__construct(sprintf('No payment gateway configuration found for alias %s', $alias));
    }
}
