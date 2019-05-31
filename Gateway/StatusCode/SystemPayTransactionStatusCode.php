<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\StatusCode;

final class SystemPayTransactionStatusCode
{
    const ERROR_STATUS = [
        'ABANDONED' => 'The transaction has been abandonned by the purchaser',
        'CANCELLED' => 'The transaction has been canceled',
        'CAPTURE_FAILED' => 'The transaction has been canceled by the bank',
        'EXPIRED' => 'The transaction has expired',
        'INITIAL' => 'The transaction is still in its initial state',
        'NOT_CREATED' => 'The transaction has not been created',
        'REFUSED' => 'The transaction has been refused',
    ];

    const PENDING_STATUS = [
        'AUTHORISED_TO_VALIDATE' => 'The transaction has been accepted but must be validated manually',
        'CAPTURED' => 'The transaction is in charge by the bank',
        'SUSPENDED' => 'The transaction has been suspended',
        'UNDER_VERIFICATION' => 'The transaction is under verification',
        'WAITING_AUTHORISATION' => 'The transaction is waiting for autorization',
        'WAITING_AUTHORISATION_TO_VALIDATE' => 'The transaction must be validated by the merchant',
    ];

    const SUCCESS_STATUS = [
        'ACCEPTED' => 'The transaction has been accepted',
        'AUTHORISED' => 'The transaction has been accepted and will be charged in few days',
    ];

    public static function isError(string $code)
    {
        return isset(self::ERROR_STATUS[$code]);
    }

    public static function getErrorStatusMessage(string $code)
    {
        if (self::isError($code)) {
            return self::ERROR_STATUS[$code];
        }
    }

    public static function isPending(string $code)
    {
        return isset(self::PENDING_STATUS[$code]);
    }

    public static function getPendingStatusMessage(string $code)
    {
        if (self::isPending($code)) {
            return self::PENDING_STATUS[$code];
        }
    }

    public static function isSuccess(string $code)
    {
        return isset(self::SUCCESS_STATUS[$code]);
    }

    public static function getSuccessStatusMessage(string $code)
    {
        if (self::isSuccess($code)) {
            return self::SUCCESS_STATUS[$code];
        }
    }
}
