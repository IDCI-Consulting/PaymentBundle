<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

final class PaymentStatus
{
    const STATUS_CREATED = 'created';

    const STATUS_FAILED = 'failed';

    const STATUS_APPROVED = 'approved';

    const STATUS_CANCELED = 'canceled';

    const STATUS_PENDING = 'pending';
}
