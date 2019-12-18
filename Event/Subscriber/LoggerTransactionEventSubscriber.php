<?php

namespace IDCI\Bundle\PaymentBundle\Event\Subscriber;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoggerTransactionEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var bool
     */
    private $enabled;

    public function __construct(LoggerInterface $logger, bool $enabled)
    {
        $this->logger = $logger;
        $this->enabled = $enabled;
    }

    public static function getSubscribedEvents()
    {
        return [
            TransactionEvent::APPROVED => [
                ['approve', 0],
            ],
            TransactionEvent::CANCELED => [
                ['cancel', 0],
            ],
            TransactionEvent::CREATED => [
                ['create', 0],
            ],
            TransactionEvent::FAILED => [
                ['fail', 0],
            ],
            TransactionEvent::PENDING => [
                ['pend', 0],
            ],
            TransactionEvent::UNVERIFIED => [
                ['unverify', 0],
            ],
        ];
    }

    public function approve(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction approved: '.$transactionEvent->getTransaction()->getId());
    }

    public function cancel(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction canceled: '.$transactionEvent->getTransaction()->getId());
    }

    public function create(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction created: '.$transactionEvent->getTransaction()->getId());
    }

    public function fail(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction failed: '.$transactionEvent->getTransaction()->getId());
    }

    public function pend(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction pending: '.$transactionEvent->getTransaction()->getId());
    }

    public function unverify(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->logger->info('Transaction unverified: '.$transactionEvent->getTransaction()->getId());
    }
}
