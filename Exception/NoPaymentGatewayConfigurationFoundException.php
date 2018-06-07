<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class NoPaymentGatewayConfigurationFoundException extends \Exception
{
    public function __construct(
        $message = 'No payment gateway configuration found',
        $code = 0,
        \Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
