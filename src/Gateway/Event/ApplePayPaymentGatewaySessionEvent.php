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
     * @var string
     */
    protected $validationUrl;

    /**
     * @var array
     */
    protected $paymentRequest = [];

    /**
     * @var array
     */
    protected $customData = [];

    /**
     * @var string
     */
    protected $sessionData = null;

    /**
     * ApplePayPaymentGatewayEvent constructor.
     */
    public function __construct(
        Request $request,
        PaymentContextInterface $paymentContext,
        string $validationUrl,
        array &$paymentRequest = [],
        array &$customData = [],
        string &$sessionData = null
    ) {
        $this->request = $request;
        $this->paymentContext = $paymentContext;
        $this->validationUrl = $validationUrl;
        $this->paymentRequest = &$paymentRequest;
        $this->customData = &$customData;
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
     * Get validation url.
     */
    public function getValidationUrl(): string
    {
        return $this->validationUrl;
    }

    /**
     * Get payment request.
     */
    public function getPaymentRequest(): array
    {
        return $this->paymentRequest;
    }

    /**
     * Set payment request
     */
    public function setPaymentRequest(array $paymentRequest): self
    {
        $this->paymentRequest = $paymentRequest;

        return $this;
    }

    /**
     * Get custom data.
     */
    public function getCustomData(): array
    {
        return $this->customData;
    }

    /**
     * Set custom data
     */
    public function setCustomData(array $customData): self
    {
        $this->customData = $customData;

        return $this;
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
