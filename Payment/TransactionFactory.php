<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use IDCI\Bundle\PaymentBundle\Payment\PaymentStatus;
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
            self::$_instance = new TransactionFactory();
        }

        return self::$_instance;
    }

    public function create(array $parameters): Transaction
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        return (new Transaction())
            ->setItemId($resolvedParameters['item_id'])
            ->setGatewayConfigurationAlias($resolvedParameters['gateway_configuration_alias'])
            ->setCustomerId($resolvedParameters['customer_id'])
            ->setCustomerEmail($resolvedParameters['customer_email'])
            ->setStatus(PaymentStatus::STATUS_CREATED)
            ->setAmount($resolvedParameters['amount'])
            ->setCurrencyCode($resolvedParameters['currency_code'])
            ->setDescription($resolvedParameters['description'])
        ;
    }

    protected function configureParameters(OptionsResolver $resolver)
    {
        $currencies = (new ISO4217())->findAll();

        $alpha3CurrencyCodes = array_map(function ($currency) {
            return $currency->getAlpha3();
        }, $currencies);

        $resolver
            ->setRequired([
                'item_id',
                'amount',
                'currency_code',
            ])
            ->setDefaults([
                'gateway_configuration_alias' => null,
                'customer_id' => null,
                'customer_email' => null,
                'description' => null,
            ])
            ->setAllowedTypes('item_id', ['int', 'string'])
            ->setAllowedTypes('gateway_configuration_alias', ['null', 'string'])
            ->setAllowedTypes('amount', ['int', 'double', 'string'])
            ->setAllowedTypes('currency_code', 'string')
            ->setAllowedTypes('customer_id', ['null', 'int', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedValues('currency_code', $alpha3CurrencyCodes)
            ->setNormalizer('amount', function (Options $options, $value) {
                return (float) $value;
            })
        ;
    }
}
