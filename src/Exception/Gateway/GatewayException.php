<?php

namespace IDCI\Bundle\PaymentBundle\Exception\Gateway;

class GatewayException extends \Exception
{
    public function getClassName()
    {
        return (new \ReflectionClass($this))->getName();
    }
}
