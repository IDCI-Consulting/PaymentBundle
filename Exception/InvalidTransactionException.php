<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class InvalidTransactionException extends \Exception
{
    public function __construct($responseCode, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('ERROR TYPE: %s. Transaction aborted !', $responseCode), $code, $previous);
    }
}
