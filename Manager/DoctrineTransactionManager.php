<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedTransactionException;

class DoctrineTransactionManager implements TransactionManagerInterface
{
    /**
     * @var ObjectManager
     */
    private $om;

    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

    public function retrieveTransactionByUuid(string $transactionUuid): Transaction
    {
        $transaction = $this
            ->om
            ->getRepository(Transaction::class)
            ->findOneBy(['id' => $transactionUuid])
        ;

        if (null === $transaction) {
            throw new UndefinedTransactionException(sprintf('No transaction found with the uuid : %s', $transactionUuid));
        }

        return $transaction;
    }
}
