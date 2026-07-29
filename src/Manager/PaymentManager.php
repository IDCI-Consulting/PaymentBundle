<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\ORM\EntityManagerInterface;
use IDCI\Bundle\PaymentBundle\Context\PaymentContext;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGateway;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfiguration as PaymentGatewayConfigurationModel;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class PaymentManager
{
    private LoggerInterface $paymentLogger;
    private PaymentSystemRegistry $paymentSystemRegistry;
    private array $paymentGatewayConfigurations;
    private ?EntityManagerInterface $em;

    public function __construct(
        LoggerInterface $paymentLogger,
        PaymentSystemRegistry $paymentSystemRegistry,
        array $paymentGatewayConfigurations,
        ?EntityManagerInterface $em = null,
    ) {
        $this->paymentLogger = $paymentLogger;
        $this->paymentSystemRegistry = $paymentSystemRegistry;
        $this->paymentGatewayConfigurations = $paymentGatewayConfigurations;
        $this->em = $em;
    }

    public function getPaymentGatewayConfigurationsFromDoctrine(): array
    {
        $paymentGatewayConfigurations = [];

        $doctrinePaymentGatewayConfigurations = $this
            ->em
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findAll()
        ;

        foreach ($doctrinePaymentGatewayConfigurations as $key => $doctrinePaymentGatewayConfiguration) {
            $paymentGatewayConfigurations[$doctrinePaymentGatewayConfiguration->getAlias()] = $doctrinePaymentGatewayConfiguration;
        }

        return $paymentGatewayConfigurations;
    }

    public function getPaymentGatewayConfigurations(): array
    {
        $paymentGatewayConfigurations = [];

        foreach ($this->paymentGatewayConfigurations as $alias => $configuration) {
            $paymentGatewayConfigurations[$alias] = $this->createPaymentGatewayConfiguration($alias, $configuration);
        }

        if (!$this->em) {
            return $paymentGatewayConfigurations;
        }

        return array_merge(
            $paymentGatewayConfigurations,
            $this->getPaymentGatewayConfigurationsFromDoctrine()
        );
    }

    public function getPaymentGateway(string $alias): PaymentGateway
    {
        $paymentGatewayConfigurations = $this->getPaymentGatewayConfigurations();

        if (!isset($paymentGatewayConfigurations[$alias])) {
            throw new NoPaymentGatewayConfigurationFoundException($alias);
        }

        return $this->createPaymentGateway($paymentGatewayConfigurations[$alias]);
    }

    public function getPaymentGateways(): array
    {
        $paymentGateways = [];

        foreach ($this->getPaymentGatewayConfigurations() as $alias => $paymentGatewayConfiguration) {
            $paymentGateways[$alias] = $this->createPaymentGateway($paymentGatewayConfiguration);
        }

        return $paymentGateways;
    }

    public function createPaymentGatewayConfiguration(string $alias, array $configuration): PaymentGatewayConfigurationModel
    {
        return (new PaymentGatewayConfigurationModel())
            ->setAlias($alias)
            ->setPaymentSystemAlias($configuration['payment_system_alias'])
            ->setEnabled($configuration['enabled'])
            ->setParameters($configuration['parameters'])
        ;
    }

    public function createPaymentGateway(PaymentGatewayConfigurationModel $paymentGatewayConfiguration): PaymentGateway
    {
        return (new PaymentGateway())
            ->setAlias($paymentGatewayConfiguration->getAlias())
            ->setPaymentSystemAlias($paymentGatewayConfiguration->getPaymentSystemAlias())
            ->setPaymentSystem($this->paymentSystemRegistry->get($paymentGatewayConfiguration->getPaymentSystemAlias()))
            ->setEnabled($paymentGatewayConfiguration->isEnabled())
            ->setParameters($paymentGatewayConfiguration->getParameters())
        ;
    }

    public function createPaymentContext(string $alias, Request $request): PaymentContext
    {
        return (new PaymentContext())
            ->setLogger($this->paymentLogger)
            ->setRequest($request)
            ->setPaymentGateway($this->getPaymentGateway($alias))
        ;
    }
}
