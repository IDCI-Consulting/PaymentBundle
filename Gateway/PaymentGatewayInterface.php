<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

interface PaymentGatewayInterface
{
    public function getParameterNames(): ?array;
}
