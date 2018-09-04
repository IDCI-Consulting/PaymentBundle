<?php

namespace IDCI\Bundle\PaymentBundle\Event\Subscriber;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use Predis\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RedisTransactionEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Client
     */
    private $redis;

    /**
     * @var bool
     */
    private $enabled;

    public function __construct(?Client $redis, bool $enabled)
    {
        $this->redis = $redis;
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
        if (!$this->enabled || null == $this->redis) {
            return;
        }

        $transaction = $transactionEvent->getTransaction();
        $this->redis->set($transaction->getId(), serialize($transaction));
    }
}
