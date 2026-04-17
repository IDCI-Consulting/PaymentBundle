<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Exception\NoTransactionFoundException;
use IDCI\Bundle\PaymentBundle\Model\Transaction as TransactionModel;

class DoctrineTransactionManager implements TransactionManagerInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(?EntityManagerInterface $em = null)
    {
        $this->em = $em;
    }

    public function saveTransaction(TransactionModel $transaction)
    {
        $this->em->persist($transaction);
        $this->em->flush();
    }

    public function retrieveTransactionById(string $id): TransactionModel
    {
        $transaction = $this
            ->em
            ->getRepository(Transaction::class)
            ->findOneBy(['id' => $id])
        ;

        if (null === $transaction) {
            throw new NoTransactionFoundException($id);
        }

        return $transaction;
    }
}
