<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use IDCI\Bundle\PaymentBundle\Model\Transaction;

interface TransactionManagerInterface
{
    public function saveTransaction(Transaction $transaction);
/*
    public function retrieveTransactionById(string $id): Transaction;

    public function retrieveTransactionByNumber(string $number): Transaction;
*/
}
