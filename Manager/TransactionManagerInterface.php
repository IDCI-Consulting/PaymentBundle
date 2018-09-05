<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use IDCI\Bundle\PaymentBundle\Model\Transaction;

interface TransactionManagerInterface
{
    public function saveTransaction(Transaction $transaction);

    public function retrieveTransactionByUuid(string $uuid): Transaction;
}
