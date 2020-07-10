<?php

namespace IDCI\Bundle\PaymentBundle\Event\Subscriber;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PAymentBundle\Manager\TransactionManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TransactionManagerEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    public function __construct(TransactionManagerInterface $transactionManager)
    {
        $this->transactionManager = $transactionManager;
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
            TransactionEvent::UNVERIFIED => [
                ['save', 0],
            ],
            TransactionEvent::UPDATED => [
                ['save', 0],
            ],
        ];
    }

    public function save(TransactionEvent $transactionEvent)
    {
        $this->transactionManager->saveTransaction($transactionEvent->getTransaction());
    }
}
