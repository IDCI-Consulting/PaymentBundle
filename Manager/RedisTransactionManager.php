<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedTransactionException;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedTransactionManagerException;
use Predis\Client;

class RedisTransactionManager implements TransactionManagerInterface
{
    private $redis;

    public function __construct(?Client $redis)
    {
        $this->redis = $redis;
    }

    public function retrieveTransactionByUuid(string $transactionUuid): Transaction
    {
        if (null === $this->redis) {
            throw new UndefinedTransactionManagerException(
                'The redis client is null, make sure you have properly configured it'
            );
        }

        $transaction = $this->redis->get($transactionUuid);

        if (null === $transaction) {
            throw new UndefinedTransactionException(
                sprintf('No transaction found with the uuid : %s', $transactionUuid)
            );
        }

        return unserialize($transaction);
    }
}
