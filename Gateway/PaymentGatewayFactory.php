<?php

namespace IDCI\Bundle\PaymentBundle\Gateway;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\UndefinedPaymentGatewayException;

class PaymentGatewayFactory
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var array
     */
    private $paymentGatewayList;

    public function __construct(ObjectManager $om, array $paymentGatewayList)
    {
        $this->om = $om;
        $this->paymentGatewayList = $paymentGatewayList;
    }

    public function getPaymentGatewayList(): array
    {
        return $this->paymentGatewayList;
    }

    public function getPaymentGatewayFQCN(string $gatewayName): string
    {
        if (!isset($this->paymentGatewayList[$gatewayName])) {
            throw new UndefinedPaymentGatewayException(sprintf('No gateway exist for the gateway name : %s', $gatewayName));
        }

        return $this->paymentGatewayList[$gatewayName];
    }

    public function buildFromGatewayName(string $gatewayName): PaymentGatewayInterface
    {
        $gatewayFQCN = $this->getPaymentGatewayFQCN($gatewayName);

        return new $gatewayFQCN();
    }

    public function buildFromAlias(string $alias): PaymentGatewayInterface
    {
        $paymentGatewayConfigurationRepository = $this->om->getRepository(PaymentGatewayConfiguration::class);

        $paymentGatewayConfiguration = $paymentGatewayConfigurationRepository->findOneBy(['alias' => $alias]);

        if (null === $paymentGatewayConfiguration) {
            throw new UndefinedPaymentGatewayException(sprintf('No gateway exist for the alias : %s', $alias));
        }

        return $this->buildFromPaymentGatewayConfiguration($paymentGatewayConfiguration);
    }

    public function buildFromPaymentGatewayConfiguration(
        PaymentGatewayConfiguration $paymentGatewayConfiguration
    ): PaymentGatewayInterface {
        $paymentGatewayFQCN = $this->getPaymentGatewayFQCN($paymentGatewayConfiguration->getGatewayName());

        return new $paymentGatewayFQCN($paymentGatewayConfiguration->getParameters());
    }
}
