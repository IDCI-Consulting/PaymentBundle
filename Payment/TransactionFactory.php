<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use Flaky\Flaky;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use Payum\ISO4217\ISO4217;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFactory
{
    /**
     * @var TransactionFactory
     */
    private static $_instance;

    public static function getInstance(): self
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function create(array $parameters): Transaction
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        return (new Transaction())
            ->setId($resolvedParameters['id'])
            ->setNumber($resolvedParameters['number'])
            ->setItemId($resolvedParameters['item_id'])
            ->setPaymentMethod($resolvedParameters['payment_method'])
            ->setGatewayConfigurationAlias($resolvedParameters['gateway_configuration_alias'])
            ->setCustomerId($resolvedParameters['customer_id'])
            ->setCustomerEmail($resolvedParameters['customer_email'])
            ->setStatus($resolvedParameters['status'])
            ->setAmount($resolvedParameters['amount'])
            ->setCurrencyCode($resolvedParameters['currency_code'])
            ->setDescription($resolvedParameters['description'])
            ->setMetadata($resolvedParameters['metadata'])
            ->setRaw($resolvedParameters['raw'])
            ->setLogged($resolvedParameters['logged'])
        ;
    }

    protected function configureParameters(OptionsResolver $resolver)
    {
        $currencies = (new ISO4217())->findAll();
        $alpha3CurrencyCodes = array_map(function ($currency) {
            return $currency->getAlpha3();
        }, $currencies);

        $resolver
            ->setRequired('item_id')->setAllowedTypes('item_id', ['int', 'string'])
            ->setRequired('amount')->setAllowedTypes('amount', ['int', 'double', 'string'])
                ->setNormalizer('amount', function (Options $options, $value) {
                    return (float) $value;
                })
            ->setRequired('currency_code')->setAllowedValues('currency_code', $alpha3CurrencyCodes)
            ->setDefault('id', Flaky::id(62))
            ->setDefault('number', null)->setAllowedTypes('number', ['null', 'int'])
            ->setDefault('gateway_configuration_alias', null)->setAllowedTypes('gateway_configuration_alias', ['null', 'string'])
            ->setDefault('payment_method', null)->setAllowedTypes('payment_method', ['null', 'string'])
            ->setDefault('customer_id', null)->setAllowedTypes('customer_id', ['null', 'int', 'string'])
            ->setDefault('customer_email', null)->setAllowedTypes('customer_email', ['null', 'string'])
            ->setDefault('description', null)->setAllowedTypes('description', ['null', 'string'])
            ->setDefault('metadata', [])->setAllowedTypes('metadata', ['null', 'array'])
            ->setDefault('raw', [])->setAllowedTypes('raw', ['null', 'array'])
            ->setDefault('status', PaymentStatus::STATUS_CREATED)->setAllowedValues('status', PaymentStatus::AVAILABLE_STATUSES)
            ->setDefault('logged', true)->setAllowedTypes('logged', ['bool'])
        ;
    }
}
