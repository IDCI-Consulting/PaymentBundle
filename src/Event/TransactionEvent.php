<?php

namespace IDCI\Bundle\PaymentBundle\Event;

use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

class TransactionEvent extends Event
{
    public const APPROVED = 'idci_payment.transaction.approved';
    public const CANCELED = 'idci_payment.transaction.canceled';
    public const CREATED = 'idci_payment.transaction.created';
    public const FAILED = 'idci_payment.transaction.failed';
    public const PENDING = 'idci_payment.transaction.pending';
    public const UNVERIFIED = 'idci_payment.transaction.unverified';
    public const UPDATED = 'idci_payment.transaction.updated';

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
