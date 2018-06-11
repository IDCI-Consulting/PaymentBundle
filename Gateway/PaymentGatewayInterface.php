<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\Entity\Payment;

interface PaymentGatewayInterface
{
    public static function getParameterNames(): ?array;

    public function createPayment(?array $parameters): Payment;

    public function buildHTMLView(): string;
}
