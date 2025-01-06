<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

class OneClickContextEvent
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
     * @var array
     */
    protected $data = [];

    /**
     * OneClickContextEvent constructor.
     */
    public function __construct(
        Request $request,
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array $data = []
    ) {
        $this->request = $request;
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
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
     * Get data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}
