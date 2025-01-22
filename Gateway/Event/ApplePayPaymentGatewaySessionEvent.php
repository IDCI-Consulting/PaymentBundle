<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

use IDCI\Bundle\PaymentBundle\Payment\PaymentContextInterface;
use Symfony\Component\HttpFoundation\Request;

class ApplePayPaymentGatewaySessionEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var PaymentContextInterface
     */
    protected $paymentContext;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $sessionData = null;

    /**
     * ApplePayPaymentGatewayEvent constructor.
     */
    public function __construct(
        Request $request,
        PaymentContextInterface $paymentContext = null,
        array $data = [],
        string &$sessionData = null
    ) {
        $this->request = $request;
        $this->paymentContext = $paymentContext;
        $this->data = $data;
        $this->sessionData = &$sessionData;
    }

    /**
     * Get request.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get paymentContext.
     */
    public function getPaymentGatewayContext(): PaymentContextInterface
    {
        return $this->paymentContext;
    }

    /**
     * Get data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get sessionData.
     */
    public function getSessionData(): ?string
    {
        return $this->sessionData;
    }

    /**
     * Set sessionData
     */
    public function setSessionData(string $sessionData): self
    {
        $this->sessionData = $sessionData;

        return $this;
    }
}
