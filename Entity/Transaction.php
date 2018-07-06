<?php

namespace IDCI\Bundle\PaymentBundle\Entity;

use IDCI\Bundle\PaymentBundle\Model\Transaction as TransactionModel;

class Transaction extends TransactionModel
{
    public function onPrePersist()
    {
        $now = new \DateTime('now');

        $this
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
    }

    public function onPreUpdate()
    {
        $this->setUpdatedAt(new \DateTime('now'));
    }
}
