<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\StatusCode;

class PaymentStatusCode
{
    const STATUS_CREATED = 'created';

    const STATUS_FAILED = 'failed';

    const STATUS_APPROVED = 'validated';

    const STATUS_CANCELED = 'canceled';

    const STATUS_PENDING = 'pending';
}
