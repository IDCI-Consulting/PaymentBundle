<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Ramsey\Uuid\Uuid;

class PaymentGatewayConfiguration implements PaymentGatewayConfigurationInterface
{
    /**
     * @var Uuid
     */
    protected $id;

    /**
     * @var string
     */
    protected $alias;

    /**
     * @var string
     */
    protected $gatewayName;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var array
     */
    protected $parameters;

    public function get(string $key)
    {
        return $this->parameters[$key];
    }

    public function set(string $key, $value): PaymentGatewayConfigurationInterface
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function __toString(): string
    {
        return $this->alias;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): PaymentGatewayConfigurationInterface
    {
        $this->alias = $alias;

        return $this;
    }

    public function getGatewayName(): ?string
    {
        return $this->gatewayName;
    }

    public function setGatewayName(string $gatewayName): PaymentGatewayConfigurationInterface
    {
        $this->gatewayName = $gatewayName;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enable): PaymentGatewayConfigurationInterface
    {
        $this->enabled = $enable;

        return $this;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function addParameter($parameterKey, $parameterValue): PaymentGatewayConfigurationInterface
    {
        $this->parameters[$parameterKey] = $parameterValue;

        return $this;
    }

    public function setParameters(array $parameters): PaymentGatewayConfigurationInterface
    {
        $this->parameters = [];

        foreach ($parameters as $parameterKey => $parameterValue) {
            $this->addParameter($parameterKey, $parameterValue);
        }

        return $this;
    }
}
