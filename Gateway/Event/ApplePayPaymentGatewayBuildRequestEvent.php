<?php

namespace IDCI\Bundle\PaymentBundle\Gateway\Event;

use IDCI\Bundle\PaymentBundle\Model\GatewayResponse;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;

class ApplePayPaymentGatewayBuildRequestEvent
{
    /**
     * @var PaymentGatewayConfigurationInterface
     */
    protected $paymentGatewayConfiguration;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * ApplePayPaymentGatewayBuildRequestEvent constructor.
     */
    public function __construct(
        PaymentGatewayConfigurationInterface $paymentGatewayConfiguration,
        array &$options = []
    ) {
        $this->paymentGatewayConfiguration = $paymentGatewayConfiguration;
        $this->options = &$options;
    }

    /**
     * Get paymentGatewayConfiguration.
     */
    public function getPaymentGatewayConfiguration(): PaymentGatewayConfigurationInterface
    {
        return $this->paymentGatewayConfiguration;
    }

    /**
     * Get options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set options.
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }
}
