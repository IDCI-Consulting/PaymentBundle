<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Ramsey\Uuid\Uuid;

class PaymentGatewayConfiguration
{
    protected Uuid $id;
    protected string $alias;
    protected string $paymentSystemAlias;
    protected bool $enabled;
    protected array $parameters;

    public function __toString(): string
    {
        return $this->getAlias();
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

    public function getPaymentSystemAlias(): ?string
    {
        return $this->paymentSystemAlias;
    }

    public function setPaymentSystemAlias(string $paymentSystemAlias): self
    {
        $this->paymentSystemAlias = $paymentSystemAlias;

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

    public function setParameters(array $parameters): self
    {
        $this->parameters = [];

        foreach ($parameters as $key => $value) {
            $this->addParameter($key, $value);
        }

        return $this;
    }

    public function addParameter($key, $value): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function hasParameter(string $key)
    {
        return isset($this->parameters[$key]);
    }

    public function getParameter(string $key)
    {
        return $this->hasParameter($key) ? $this->parameters[$key] : null;
    }
}
