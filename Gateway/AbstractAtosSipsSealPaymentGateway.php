<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

abstract class AbstractAtosSipsSealPaymentGateway extends AbstractPaymentGateway
{
    abstract protected function getServerUrl(): string;
}
