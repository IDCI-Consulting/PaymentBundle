<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Ramsey\Uuid\Uuid;

class PaymentGatewayConfiguration implements PaymentGatewayConfigurationInterface
{
    protected Uuid $id;
    protected string $alias;
    protected string $gatewayName;
    protected bool $enabled;
    protected array $parameters;

    public function get(string $key)
    {
        return $this->parameters[$key];
    }

    public function set(string $key, $value): self
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

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getGatewayName(): ?string
    {
        return $this->gatewayName;
    }

    public function setGatewayName(string $gatewayName): self
    {
        $this->gatewayName = $gatewayName;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enable): self
    {
        $this->enabled = $enable;

        return $this;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function addParameter($parameterKey, $parameterValue): self
    {
        $this->parameters[$parameterKey] = $parameterValue;

        return $this;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = [];

        foreach ($parameters as $parameterKey => $parameterValue) {
            $this->addParameter($parameterKey, $parameterValue);
        }

        return $this;
    }
}
