<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Event\Subscriber;

use PHPUnit\Framework\TestCase;
use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Event\Subscriber\DoctrineTransactionEventSubscriber;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;

class DoctrineTransactionEventSubscriberTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $om;

    public function setUp()
    {
        $this->om = $this->getMockBuilder(ObjectManager::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $transaction = $this->getMockBuilder(Transaction::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->transactionEvent = $this->getMockBuilder(TransactionEvent::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->transactionEvent
            ->method('getTransaction')
            ->willReturn($transaction)
        ;
    }

    public function testSave()
    {
        $this->doctrineTransactionEventSubscriber = new DoctrineTransactionEventSubscriber(
            $this->om,
            true
        );

        $this->om
            ->expects($this->once())
            ->method('persist')
        ;

        $this->om
            ->expects($this->once())
            ->method('flush')
        ;

        $this->doctrineTransactionEventSubscriber->save($this->transactionEvent);
    }

    public function testNotSave()
    {
        $this->doctrineTransactionEventSubscriber = new DoctrineTransactionEventSubscriber(
            $this->om,
            false
        );

        $this->om
            ->expects($this->never())
            ->method('persist')
        ;

        $this->om
            ->expects($this->never())
            ->method('flush')
        ;

        $this->doctrineTransactionEventSubscriber->save($this->transactionEvent);
    }
}
