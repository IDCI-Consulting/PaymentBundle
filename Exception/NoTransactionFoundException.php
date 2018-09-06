<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class NoTransactionFoundException extends \InvalidArgumentException
{
    public function __construct(string $uuid)
    {
        parent::__construct(sprintf('No transaction found with the uuid : %s', $uuid));
    }
}
