<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;

interface TransactionManagerInterface
{
    public function retrieveTransactionByUuid(string $uuid): Transaction;
}
