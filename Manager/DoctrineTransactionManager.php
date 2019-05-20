<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\NoTransactionFoundException;
use IDCI\Bundle\PaymentBundle\Model\Transaction as TransactionModel;

class DoctrineTransactionManager implements TransactionManagerInterface
{
    /**
     * @var ObjectManager
     */
    private $om;

    public function __construct(?ObjectManager $om = null)
    {
        $this->om = $om;
    }

    public function saveTransaction(TransactionModel $transaction)
    {
        $this->om->persist($transaction);
        $this->om->flush();
    }

    public function retrieveTransactionByUuid(string $transactionUuid): TransactionModel
    {
        $transaction = $this
            ->om
            ->getRepository(Transaction::class)
            ->findOneBy(['id' => $transactionUuid])
        ;

        if (null === $transaction) {
            throw new NoTransactionFoundException($transactionUuid);
        }

        return $transaction;
    }
}
