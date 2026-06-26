<?php

namespace IDCI\Bundle\PaymentBundle\Tests\Event\Subscriber;

use PHPUnit\Framework\TestCase;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Event\Subscriber\LoggerTransactionEventSubscriber;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use Psr\Log\LoggerInterface;

class LoggerTransactionEventSubscriberTest extends TestCase
{
    public function setUp()
    {
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
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

    public function testApproved()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            true
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Transaction approved: '.$this->transactionEvent->getTransaction()->getId())
        ;

        $this->loggerTransactionEventSubscriber->approve($this->transactionEvent);
    }

    public function testNotApproved()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            false
        );

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        $this->loggerTransactionEventSubscriber->approve($this->transactionEvent);
    }

    public function testCancelled()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            true
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Transaction canceled: '.$this->transactionEvent->getTransaction()->getId())
        ;

        $this->loggerTransactionEventSubscriber->cancel($this->transactionEvent);
    }

    public function testNotCancelled()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            false
        );

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        $this->loggerTransactionEventSubscriber->cancel($this->transactionEvent);
    }

    public function testCreated()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            true
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Transaction created: '.$this->transactionEvent->getTransaction()->getId())
        ;

        $this->loggerTransactionEventSubscriber->create($this->transactionEvent);
    }

    public function testNotCreated()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            false
        );

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        $this->loggerTransactionEventSubscriber->create($this->transactionEvent);
    }

    public function testFailed()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            true
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Transaction failed: '.$this->transactionEvent->getTransaction()->getId())
        ;

        $this->loggerTransactionEventSubscriber->fail($this->transactionEvent);
    }

    public function testNotFailed()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            false
        );

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        $this->loggerTransactionEventSubscriber->fail($this->transactionEvent);
    }

    public function testPending()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            true
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Transaction pending: '.$this->transactionEvent->getTransaction()->getId())
        ;

        $this->loggerTransactionEventSubscriber->pend($this->transactionEvent);
    }

    public function testNotPending()
    {
        $this->loggerTransactionEventSubscriber = new LoggerTransactionEventSubscriber(
            $this->logger,
            false
        );

        $this->logger
            ->expects($this->never())
            ->method('info')
        ;

        $this->loggerTransactionEventSubscriber->pend($this->transactionEvent);
    }
}
