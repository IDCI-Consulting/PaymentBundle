<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function getParameterNames(): ?array
    {
        return get_object_vars($this);
    }
}
