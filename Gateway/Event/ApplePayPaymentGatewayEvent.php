<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

class ApplePayPaymentGatewayEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var PaymentGatewayConfigurationInterface
     */
    protected $paymentGatewayConfiguration;

    /**
     * @var GatewayResponse
     */
    protected $gatewayResponse;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * ApplePayPaymentGatewayEvent constructor.
     */
    public function __construct(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        GatewayResponse $gatewayResponse,
        array $data = []
    ) {
        $this->request = $request;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->gatewayResponse = $gatewayResponse;
        $this->data = $data;
    }

    /**
     * Get request.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get paymentGatewayConfiguration.
     */
    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface
    {
        return $this->paymentGatewayConfiguration;
    }

    /**
     * Get gatewayResponse.
     */
    public function getGatewayResponse(): GatewayResponse
    {
        return $this->gatewayResponse;
    }

    /**
     * Get data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}
