<?php

namespace IDCI\Bundle\PaymentBundle\Event\Subscriber;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DoctrineTransactionEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var bool
     */
    private $enabled;

    public function __construct(ObjectManager $om, bool $enabled)
    {
        $this->om = $om;
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
        ];
    }

    public function approve(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $transaction = $transactionEvent->getTransaction()->setStatus(PaymentStatus::STATUS_APPROVED);
        $this->om->flush();
    }

    public function cancel(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $transaction = $transactionEvent->getTransaction()->setStatus(PaymentStatus::STATUS_CANCELED);
        $this->om->flush();
    }

    public function create(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $transaction = $transactionEvent->getTransaction()->setStatus(PaymentStatus::STATUS_CREATED);
        $this->om->persist($transaction);
        $this->om->flush();
    }

    public function fail(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $transaction = $transactionEvent->getTransaction()->setStatus(PaymentStatus::STATUS_FAILED);
        $this->om->flush();
    }
}
