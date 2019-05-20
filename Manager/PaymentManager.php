<?php

namespace IDCI\Bundle\PaymentBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use IDCI\Bundle\PaymentBundle\Entity\PaymentGatewayConfiguration;
use IDCI\Bundle\PaymentBundle\Exception\NoPaymentGatewayConfigurationFoundException;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGatewayRegistryInterface;
use IDCI\Bundle\PaymentBundle\Model\PaymentGatewayConfigurationInterface;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContext;
use IDCI\Bundle\PaymentBundle\Payment\PaymentContextInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
    private $paymentGatewayConfigurations;

    public function __construct(
        ?ObjectManager $om = null,
        PaymentGatewayRegistryInterface $paymentGatewayRegistry,
        TransactionManagerInterface $transactionManager,
        EventDispatcherInterface $dispatcher,
        array $paymentGatewayConfigurations
    ) {
        $this->om = $om;
        $this->dispatcher = $dispatcher;
        $this->paymentGatewayRegistry = $paymentGatewayRegistry;
        $this->transactionManager = $transactionManager;
        $this->paymentGatewayConfigurations = $paymentGatewayConfigurations;
    }

    public function getAllPaymentGatewayConfigurationFromDoctrine(): array
    {
        $paymentGatewayConfigurations = [];

        $doctrinePaymentGatewayConfigurations = $this
            ->om
            ->getRepository(PaymentGatewayConfiguration::class)
            ->findAll()
        ;

        foreach ($doctrinePaymentGatewayConfigurations as $key => $doctrinePaymentGatewayConfiguration) {
            $paymentGatewayConfigurations[$doctrinePaymentGatewayConfiguration->getAlias()] = $doctrinePaymentGatewayConfiguration;
        }

        return $paymentGatewayConfigurations;
    }

    public function getAllPaymentGatewayConfiguration(): array
    {
        $paymentGatewayConfigurations = [];

        foreach ($this->paymentGatewayConfigurations as $alias => $configuration) {
            $paymentGatewayList = $this->paymentGatewayRegistry->getAll();
            $paymentGatewayFQCN = get_class($paymentGatewayList[$configuration['gateway_name']]);

            foreach ($paymentGatewayFQCN::getParameterNames() as $parameterName) {
                if (!array_key_exists($parameterName, $configuration['parameters'])) {
                    throw new \UnexpectedValueException(
                        'Payment Manager : Payment Gateway Configuration error : '.
                        sprintf(
                            'Parameter %s not found for payment gateway configuration %s',
                            $parameterName, $alias
                        )
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

        if (!$this->om) {
            return $paymentGatewayConfigurations;
        }

        return array_merge(
            $paymentGatewayConfigurations,
            $this->getAllPaymentGatewayConfigurationFromDoctrine()
        );
    }

    public function getPaymentGatewayConfiguration(string $alias): PaymentGatewayConfigurationInterface
    {
        $paymentGatewayConfigurations = $this->getAllPaymentGatewayConfiguration();

        if (!isset($paymentGatewayConfigurations[$alias])) {
            throw new NoPaymentGatewayConfigurationFoundException($alias);
        }

        return $paymentGatewayConfigurations[$alias];
    }

    public function createPaymentContextByAlias(string $alias): PaymentContextInterface
    {
        $paymentGatewayConfiguration = $this->getPaymentGatewayConfiguration($alias);

        return new PaymentContext(
            $this->dispatcher,
            $paymentGatewayConfiguration,
            $this->paymentGatewayRegistry->get($paymentGatewayConfiguration->getGatewayName()),
            $this->transactionManager
        );
    }
}
