<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class UnexpectedPaymentGatewayException extends \InvalidArgumentException
{
    public function __construct($value, $expectedType)
    {
        parent::__construct(sprintf(
            'Expected argument of type "%s", "%s" given',
            $expectedType,
            is_object($value) ? get_class($value) : gettype($value)
        ));
    }
}
