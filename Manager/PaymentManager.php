<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\InvalidPaymentGatewayConfigurationException;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistryInterface;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContext;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContextInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PaymentManager
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var PaymentGatewayRegistryInterface
     */
    private $paymentGatewayRegistry;

    /**
     * @var TransactionManagerInterface
     */
    private $transactionManager;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var array
     */
    private $gatewayConfigurations;

    public function __construct(
        ObjectManager $om,
        PaymentGatewayRegistryInterface $paymentGatewayRegistry,
        TransactionManagerInterface $transactionManager,
        EventDispatcher $dispatcher,
        array $gatewayConfigurations
    ) {
        $this->om = $om;
        $this->dispatcher = $dispatcher;
        $this->paymentGatewayRegistry = $paymentGatewayRegistry;
        $this->transactionManager = $transactionManager;
        $this->gatewayConfigurations = $gatewayConfigurations;
    }

    public function getAllPaymentGatewayConfigurationFromDoctrine(): array
    {
        $paymentGatewayConfigurations = $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findAll()
        ;

        foreach ($paymentGatewayConfigurations as $key => $paymentGatewayConfiguration) {
            $paymentGatewayConfigurations[$paymentGatewayConfiguration->getAlias()] = $paymentGatewayConfiguration;
            unset($paymentGatewayConfigurations[$key]);
        }

        return $paymentGatewayConfigurations;
    }

    public function getAllPaymentGatewayConfigurationFromConfiguration(): array
    {
        $paymentGatewayConfigurations = [];

        foreach ($this->gatewayConfigurations as $alias => $configuration) {
            if (!isset($configuration['gateway_name'])) {
                throw new InvalidPaymentGatewayConfigurationException(
                    sprintf('No gateway name given for the payment gateway configuration %s', $alias)
                );
            }

            $paymentGatewayList = $this->paymentGatewayRegistry->getAll();
            $paymentGatewayFQCN = get_class($paymentGatewayList[$configuration['gateway_name']]);

            foreach ($paymentGatewayFQCN::getParameterNames() as $parameterName) {
                if (!array_key_exists($parameterName, $configuration['parameters'])) {
                    throw new InvalidPaymentGatewayConfigurationException(
                        sprintf('Parameter %s not found for payment gateway configuration %s', $parameterName, $alias)
                    );
                }
            }

            $paymentGatewayConfigurations[$alias] = (new PaymentGatewayConfiguration())
                ->setAlias($alias)
                ->setGatewayName($configuration['gateway_name'])
                ->setEnabled($configuration['enabled'])
                ->setParameters($configuration['parameters'])
            ;
        }

        return $paymentGatewayConfigurations;
    }

    public function getAllPaymentGatewayConfiguration(): array
    {
        return array_merge(
            $this->getAllPaymentGatewayConfigurationFromDoctrine(),
            $this->getAllPaymentGatewayConfigurationFromConfiguration()
        );
    }

    public function createPaymentContextByAlias(string $alias): PaymentContextInterface
    {
        $paymentGatewayConfigurations = $this->getAllPaymentGatewayConfiguration();

        if (!isset($paymentGatewayConfigurations[$alias])) {
            throw new NoPaymentGatewayConfigurationFoundException();
        }

        $paymentGatewayConfiguration = $paymentGatewayConfigurations[$alias];

        return new PaymentContext(
            $this->dispatcher,
            $paymentGatewayConfiguration,
            $this->paymentGatewayRegistry->get($paymentGatewayConfiguration->getGatewayName()),
            $this->transactionManager
        );
    }
}
