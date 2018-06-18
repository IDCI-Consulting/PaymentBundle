<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class InvalidTransactionException extends \Exception
{
    public function __construct($responseCode, $code = 0, \Exception $previous = null)
    {
        if ('3D_ATTEMPT' === $responseCode) {
            $message = '3D_ATTEMPT: payment is impossible';
        } elseif ('3D_ERROR' === $responseCode) {
            $message = '3D_ERROR: technical error';
        } else {
            $message = 'Unknown error';
        }

        parent::__construct($message, $code, $previous);
    }
}
