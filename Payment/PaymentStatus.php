<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

final class PaymentStatus
{
    const STATUS_CREATED = 'created';
    const STATUS_FAILED = 'failed';
    const STATUS_UNVERIFIED = 'unverified';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELED = 'canceled';
    const STATUS_PENDING = 'pending';

    const AVAILABLE_STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_FAILED,
        self::STATUS_UNVERIFIED,
        self::STATUS_APPROVED,
        self::STATUS_CANCELED,
        self::STATUS_PENDING,
    ];
}
