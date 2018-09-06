<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class UnexpectedAtosSipsResponseCodeException extends \UnexpectedValueException
{
    public function __construct($errorCode)
    {
        $messages = [
            '02' => 'Authorization limit on card exceeded',
            '03' => 'Invalid merchant contract',
            '05' => 'Authorization denied',
            '11' => 'The PAN is in opposition',
            '12' => 'Invalid transaction, check the parameters transferred in the request',
            '14' => 'Invalid payment method details',
            '17' => 'Cancellation of the buyer',
            '30' => 'Format error',
            '34' => 'Suspicion of fraud (false seal)',
            '54' => 'Payment method validity date expired',
            '75' => 'Number of attempts to enter payment method details under Sips Paypage exceeded',
            '90' => 'Service temporarily unavailable',
            '94' => 'Duplicate transaction: the transactionReference of the transaction is already used',
            '97' => 'Time expired, transaction refused',
            '99' => 'Temporary problem with the payment server.',
        ];

        $message = isset($messages[$errorCode]) ? $messages[$errorCode] : sprintf('Unkwown error code : %s', $errorCode);

        parent::__construct($message);
    }
}
