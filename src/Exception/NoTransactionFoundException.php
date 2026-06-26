<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class NoTransactionFoundException extends \InvalidArgumentException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('No transaction found with id: %s', $id));
    }
}
