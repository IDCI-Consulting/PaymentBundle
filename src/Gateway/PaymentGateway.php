<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use IDCI\Bundle\PaymentBundle\System\PaymentSystemInterface;

class PaymentGateway
{
    private string $alias;

    private string $paymentSystemAlias;

    private bool $enabled;

    private array $parameters;

    private PaymentSystemInterface $paymentSystem;

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getPaymentSystemAlias(): string
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

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getPaymentSystem(): PaymentSystemInterface
    {
        return $this->paymentSystem;
    }

    public function setPaymentSystem(PaymentSystemInterface $paymentSystem): self
    {
        $this->paymentSystem = $paymentSystem;

        return $this;
    }
}