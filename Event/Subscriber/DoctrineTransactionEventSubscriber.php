<?php

namespace IDCI\Bundle\PaymentBundle\Event\Subscriber;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
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
                ['save', 0],
            ],
            TransactionEvent::CANCELED => [
                ['save', 0],
            ],
            TransactionEvent::CREATED => [
                ['save', 0],
            ],
            TransactionEvent::FAILED => [
                ['save', 0],
            ],
            TransactionEvent::PENDING => [
                ['save', 0],
            ],
        ];
    }

    public function save(TransactionEvent $transactionEvent)
    {
        if (!$this->enabled) {
            return;
        }

        $this->om->persist($transactionEvent->getTransaction());
        $this->om->flush();
    }
}
