<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class MandatoryParametersNotFoundException extends \Exception
{
    public function __construct($mandatoryParameters, $code = 0, \Exception $previous = null)
    {
        parent::__construct(
            sprintf('The parameters [%s] are required', implode(', ', $mandatoryParameters)),
            $code,
            $previous
        );
    }
}
