<?php

namespace IDCI\Bundle\PaymentBundle\Entity;

use IDCI\Bundle\PaymentBundle\Model\TransactionNotification as TransactionNotificationModel;

class TransactionNotification extends TransactionNotificationModel
{
    public function onPrePersist()
    {
        $now = new \DateTime('now');

        $this
            ->setCreatedAt($now)
        ;
    }
}
