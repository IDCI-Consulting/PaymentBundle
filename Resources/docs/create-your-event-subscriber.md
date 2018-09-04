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
            ['methodCalledOnCreated', 0],
        ],
        TransactionEvent::FAILED => [
            ['methodCalledOnFailed', 0],
        ],
        TransactionEvent::PENDING => [
            ['methodCalledOnPending', 0],
        ],
    ];
}

// example method called on approve
public function methodCalledOnApprove(TransactionEvent $transactionEvent)
{
    // what to do if the transaction is approved
}
```

Your new event subscriber will be automaticaly bind to [TransactionEvent](../../Event/TransactionEvent.php) dispatch and when the status of the transaction change your method will be called.
