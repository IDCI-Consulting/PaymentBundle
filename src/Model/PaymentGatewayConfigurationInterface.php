<?php

namespace IDCI\Bundle\PaymentBundle\Model;

interface PaymentGatewayConfigurationInterface
{
    public function get(string $key);

    public function set(string $key, $value): PaymentGatewayConfigurationInterface;

    public function getAlias(): ?string;

    public function setAlias(string $alias): PaymentGatewayConfigurationInterface;

    public function getGatewayName(): ?string;

    public function setGatewayName(string $gatewayName): PaymentGatewayConfigurationInterface;

    public function isEnabled(): bool;

    public function setEnabled(bool $enable): PaymentGatewayConfigurationInterface;

    public function getParameters();

    public function addParameter($parameterKey, $parameterValue): PaymentGatewayConfigurationInterface;

    public function setParameters(array $parameters): PaymentGatewayConfigurationInterface;
}
