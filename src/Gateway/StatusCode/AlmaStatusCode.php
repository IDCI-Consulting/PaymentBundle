<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\StatusCode;

final class AlmaStatusCode
{
    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_SCORED_NO = 'scored_no';
    const STATUS_SCORED_YES = 'scored_yes';
    const STATUS_SCORED_MAYBE = 'scored_maybe';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PAID = 'paid';

    const STATUS = [
        'not_started' => 'The payment has been created',
        'scored_no' => 'The payment in installments is refused by Alma',
        'scored_yes' => 'The payment in installments is accepted by Alma',
        'scored_maybe' => 'Alma needs more information to decide whether to accept the payment',
        'in_progress' => 'Payment in progress - at least one due date has been paid, but there are still unpaid due dates',
        'paid' => 'The customer no longer owes Alma any money',
    ];

    public static function getStatusMessage(string $status)
    {
        return self::STATUS[$status];
    }
}
