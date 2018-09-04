How to create your own transaction manager
--------------------------------------

## Introduction

Q: What's a transaction manager ?  
A: It is used to retrieve your transactions from a specific stockage method (ex: Doctrine, Redis, ...)

## Learn by example

A transaction manager implement the interface [TransactionManagerInterface](../../Manager/TransactionManagerInterface) that contain only one method:

```php
<?php

public function retrieveTransactionByUuid(string $transactionUuid): Transaction

```

This method is called by the [PaymentContext](../../Payment/PaymentContext)

## How to use it instead of DoctrineTransactionManager ?

In your configuration :

```yaml
# services.yml
IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface: '@YourBundle\YourPath\NewTransactionManager'
```
