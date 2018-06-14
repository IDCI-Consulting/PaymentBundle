<?php

namespace IDCI\Bundle\PaymentBundle\Model;

interface PaymentGatewayConfigurationInterface
{
    public function get(string $key);

    public function set(string $key, $value): PaymentGatewayConfigurationInterface;

    public function getAlias(): ?string;

    public function setAlias(string $alias): self;

    public function getGatewayName(): ?string;

    public function setGatewayName(string $gatewayName): self;

    public function isEnabled(): bool;

    public function setEnabled(bool $enable): self;

    public function getParameters();

    public function addParameter($parameterKey, $parameterValue): self;

    public function setParameters(array $parameters): self;
}
