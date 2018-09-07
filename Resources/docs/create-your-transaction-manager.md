How to create your own transaction manager
------------------------------------------

## Introduction

Q: What's a transaction manager ?  
A: It is used to retrieve and save your transactions with a specific stockage method (ex: Doctrine, Redis, ...)

## Learn by example (for redis)

A transaction manager must implement the interface [TransactionManagerInterface](../../Manager/TransactionManagerInterface.php).
This is a little exemple of manager working with Redis.
```php
<?php

namespace MyBundle\Manager;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\NoTransactionFoundException;
use Predis\Client;

class RedisTransactionManager implements TransactionManagerInterface
{
    private $redis;

    public function __construct(Client $redis)
    {
        $this->redis = $redis;
    }

    public function saveTransaction(Transaction $transaction)
    {
        $this->redis->set($transaction->getId(), serialize($transaction));
    }

    public function retrieveTransactionByUuid(string $transactionUuid): Transaction
    {
        $transaction = $this->redis->get($transactionUuid);

        if (null === $transaction) {
            throw new NoTransactionFoundException($transactionUuid);
        }

        return unserialize($transaction);
    }
}


```

This method is called by the [PaymentContext](../../Payment/PaymentContext.php)

## How to use it instead of DoctrineTransactionManager ?

In your configuration :

```yaml
# services.yml
MyBundle\Manager\RedisTransactionManager:
    arguments:
        $redis: '@snc_redis.default'
IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface: '@MyBundle\Manager\RedisTransactionManager'
```
