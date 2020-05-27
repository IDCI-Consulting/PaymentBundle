<?php

namespace IDCI\Bundle\PaymentBundle\Event;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

class TransactionEvent extends Event
{
    const APPROVED = 'idci_payment.transaction.approved';
    const CANCELED = 'idci_payment.transaction.canceled';
    const CREATED = 'idci_payment.transaction.created';
    const FAILED = 'idci_payment.transaction.failed';
    const PENDING = 'idci_payment.transaction.pending';
    const UNVERIFIED = 'idci_payment.transaction.unverified';

    protected $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getTransaction()
    {
        return $this->transaction;
    }
}
