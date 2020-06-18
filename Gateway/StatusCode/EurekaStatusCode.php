<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\StatusCode;

final class EurekaStatusCode
{
    const STATUS = [
        '1' => 'Refused',
        '2' => 'Refused by bank',
        '3' => 'Technical error',
        '4' => 'Pending',
        '5' => 'Unknown',
        '6' => 'Canceled',
    ];

    public static function getStatusMessage(string $code)
    {
        return self::STATUS[$code];
    }
}
