<?php

namespace IDCI\Bundle\PaymentBundle\Exception\Gateway\Eureka;

use IDCI\Bundle\PaymentBundle\Exception\Gateway\GatewayException;
use IDCI\Bundle\PaymentBundle\Gateway\Client\EurekaPaymentGatewayClient;

class NotEligibleCustomerException extends GatewayException
{
    /**
     * @var string
     */
    private $scoreType;

    public function __construct(string $message, string $scoreType)
    {
        if (EurekaPaymentGatewayClient::SCORE_V3 !== $scoreType && EurekaPaymentGatewayClient::SCORE_CCL !== $scoreType) {
            throw new \InvalidArgumentException(
                sprintf('The scoring type "%s" is not supported. Supported values: %s, %s', $scoreType, EurekaPaymentGatewayClient::SCORE_V3, EurekaPaymentGatewayClient::SCORE_CCL)
            );
        }

        $this->scoreType = $scoreType;

        parent::__construct(
            sprintf(
                'You are not eligible to %s payment. Message: %s',
                EurekaPaymentGatewayClient::SCORE_V3 === $scoreType ? 'CB3X/CB4X' : 'CB10X',
                $message
            )
        );
    }

    /**
     * Retrieve the score type used by customer to evalute eligibility.
     *
     * @method getScoreType
     *
     * @return string score type
     */
    public function getScoreType(): string
    {
        return $this->scoreType;
    }
}
