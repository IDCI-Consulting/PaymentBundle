How to create your own event subscriber
---------------------------------------

## Introduction

Q: Why do i need to create my own event subscriber  
A: Everytime the transaction status is modified a new event is dispatched so you can add your own logic to it (ex: send an email).

Q: What kind of transaction event can i bind to ?  
A:
There are five possiblities, an event is dispatched when the transaction is :
- created
- pending
- approved
- canceled
- failed

## Learn by example

All your event subscriber must be using [TransactionEvent](../../Event/TransactionEvent.php) to be bind to it with one of the event type possibility listed above

```php
<?php

namespace MyBundle\Event\Subscriber;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExempleEventSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            TransactionEvent::APPROVED => [
                ['methodCalledOnApprove', 0],
            ],
            TransactionEvent::CANCELED => [
                ['methodCalledOnCancel', 0],
            ],
            TransactionEvent::CREATED => [
                ['methodCalledOnCreate', 0],
            ],
            TransactionEvent::FAILED => [
                ['methodCalledOnFail', 0],
            ],
            TransactionEvent::PENDING => [
                ['methodCalledOnPending', 0],
            ],
        ];
    }

    public function methodCalledOnApprove(TransactionEvent $transactionEvent)
    {
        // what to do if the transaction is approved
    }

    public function methodCalledOnCancel(TransactionEvent $transactionEvent)
    {
        // what to do if the transaction is canceled
    }

    public function methodCalledOnCreate(TransactionEvent $transactionEvent)
    {
        // what to do if the transaction is created
    }

    public function methodCalledOnFail(TransactionEvent $transactionEvent)
    {
        // what to do if the transaction has failed
    }

    public function methodCalledOnPending(TransactionEvent $transactionEvent)
    {
        // what to do if the transaction is suspended
    }
}
```

Your new event subscriber will be automaticaly bind to [TransactionEvent](../../Event/TransactionEvent.php) dispatch and when the status of the transaction change your methods will be called.

## Usage example with swiftmailer

Here's a little example of what you can do with an event subscriber

```php
<?php

namespace MyBundle\Event\Subscriber;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SwiftMailerTransactionEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Swift_Mailer
     */
    private $mailer;

    public function __construct(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public static function getSubscribedEvents()
    {
        return [
            TransactionEvent::APPROVED => [
                ['approve', 0],
            ],
        ];
    }

    public function approve(TransactionEvent $transactionEvent)
    {
        $transaction = $transactionEvent->getTransaction();

        $message = (new Swift_Message('Transaction approved'))
            ->setTo($transaction->getCustomerEmail())
            ->setBody(
                sprintf(
                    'Your transaction of %s %s have been approved',
                    $transaction->getAmount() / 100,
                    $transaction->getCurrencyCode()
                )
            )
        ;

        $this->mailer->send($message);
    }
}

```
