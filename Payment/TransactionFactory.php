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
                'id' => Flaky::id(62),
                'number' => null,
                'gateway_configuration_alias' => null,
                'payment_method' => null,
                'customer_id' => null,
                'customer_email' => null,
                'description' => null,
                'metadata' => [],
                'raw' => [],
                'status' => PaymentStatus::STATUS_CREATED,
            ])
            ->setAllowedTypes('item_id', ['int', 'string'])
            ->setAllowedTypes('number', ['null', 'int'])
            ->setAllowedTypes('gateway_configuration_alias', ['null', 'string'])
            ->setAllowedTypes('payment_method', ['null', 'string'])
            ->setAllowedTypes('amount', ['int', 'double', 'string'])
            ->setAllowedTypes('currency_code', 'string')
            ->setAllowedTypes('customer_id', ['null', 'int', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedTypes('metadata', ['null', 'array'])
            ->setAllowedTypes('raw', ['null', 'array'])
            ->setAllowedTypes('status', ['string'])
            ->setAllowedValues('status', [
                PaymentStatus::STATUS_APPROVED,
                PaymentStatus::STATUS_CANCELED,
                PaymentStatus::STATUS_CREATED,
                PaymentStatus::STATUS_FAILED,
                PaymentStatus::STATUS_PENDING,
                PaymentStatus::STATUS_UNVERIFIED,
            ])
            ->setAllowedValues('currency_code', $alpha3CurrencyCodes)
            ->setNormalizer('amount', function (Options $options, $value) {
                return (float) $value;
            })
        ;
    }
}
