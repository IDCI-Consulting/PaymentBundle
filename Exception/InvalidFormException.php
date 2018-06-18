<?php

namespace IDCI\Bundle\PaymentBundle\Exception;

class InvalidFormException extends \Exception
{
    public function __construct($sipsResponse, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('Atos Sips form error : %s', $sipsResponse->getRedirectionStatusMessage()), $code, $previous);
    }
}
