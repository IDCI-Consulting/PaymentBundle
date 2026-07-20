<?php

namespace IDCI\Bundle\PaymentBundle\Context;

final class TransactionStatus
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_CREATED = 'created';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNVERIFIED = 'unverified';

    public const AVAILABLE_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_CANCELED,
        self::STATUS_CREATED,
        self::STATUS_FAILED,
        self::STATUS_PENDING,
        self::STATUS_UNVERIFIED,
    ];
}
