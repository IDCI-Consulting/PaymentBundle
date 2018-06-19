<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

use IDCI\Bundle\PaymentBundle\Gateway\AbstractPaymentGateway;

class UnrecognizedGatewayEnvironmentException extends \Exception
{
    public function __construct($environment, $code = 0, \Exception $previous = null)
    {
        parent::__construct(
            sprintf(
                'Unrecognized given environnement "%s", must be "%s" or "%s"',
                $environment,
                AbstractPaymentGateway::TEST_ENVIRONMENT,
                AbstractPaymentGateway::PROD_ENVIRONMENT
            ),
            $code,
            $previous
        );
    }
}
