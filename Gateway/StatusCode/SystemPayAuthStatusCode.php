<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\StatusCode;

final class SystemPayAuthStatusCode
{
    const STATUS = [
        '03' => 'Problem with autorization servers',
        '05' => 'The bank has refused the transaction',
        '51' => 'Insufficient funds',
        '56' => 'The credit card given does\'t exists',
        '57' => 'The bank has refused the transaction',
        '59' => 'Suspected fraud',
        '60' => 'Problem with autorization servers',
    ];

    public static function hasStatus(string $code)
    {
        return isset(self::STATUS[$code]);
    }

    public static function getStatusMessage(string $code)
    {
        return self::STATUS[$code];
    }
}
