<?php

namespace IDCI\Bundle\PaymentBundle\Factory;

use Flaky\Flaky;
use IDCI\Bundle\PaymentBundle\Context\TransactionStatus;
use IDCI\Bundle\PaymentBundle\Entity\Transaction;
use Payum\ISO4217\ISO4217;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransactionFactory
{
    private static ?TransactionFactory $_instance = null;

    public static function getInstance(): self
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function create(array $data): Transaction
    {
        $resolver = new OptionsResolver();
        $this->configure($resolver);
        $resolvedData = $resolver->resolve($data);

        return (new Transaction())
            ->setId($resolvedData['id'])
            ->setNumber($resolvedData['number'])
            ->setPaymentGatewayConfigurationAlias($resolvedData['payment_gateway_configuration_alias'])
            ->setPaymentMethod($resolvedData['payment_method'])
            ->setItemReference($resolvedData['item_reference'])
            ->setCustomerReference($resolvedData['customer_reference'])
            ->setCustomerEmail($resolvedData['customer_email'])
            ->setStatus($resolvedData['status'])
            ->setAmount($resolvedData['amount'])
            ->setCurrencyCode($resolvedData['currency_code'])
            ->setDescription($resolvedData['description'])
            ->setMetadata($resolvedData['metadata'])
        ;
    }

    public function configure(OptionsResolver $resolver): void
    {
        $currencies = (new ISO4217())->findAll();

        $alpha3CurrencyCodes = array_map(function ($currency) {
            return $currency->getAlpha3();
        }, $currencies);

        $resolver
            ->setRequired('id')->setAllowedTypes('id', ['string'])
            ->setDefault('number', null)->setAllowedTypes('number', ['null', 'int'])
                ->setNormalizer('number', function(Options $options, $value): ?int {
                    if (is_string($value)) {
                        return (int)$value;
                    }

                    return $value;
                })
            ->setRequired('payment_gateway_configuration_alias')->setAllowedTypes('payment_gateway_configuration_alias', ['string'])
            ->setDefault('payment_method', null)->setAllowedTypes('payment_method', ['null', 'string'])
            ->setRequired('item_reference')->setAllowedTypes('item_reference', ['string'])
            ->setRequired('customer_reference')->setAllowedTypes('customer_reference', ['string'])
            ->setDefault('customer_email', null)->setAllowedTypes('customer_email', ['null', 'string'])
            ->setDefault('status', TransactionStatus::STATUS_CREATED)->setAllowedValues('status', TransactionStatus::AVAILABLE_STATUSES)
            ->setRequired('amount')->setAllowedTypes('amount', ['int', 'string'])
                ->setNormalizer('amount', function(Options $options, $value): int {
                    if (is_string($value)) {
                        return (int)$value;
                    }

                    return $value;
                })
            ->setRequired('currency_code')->setAllowedValues('currency_code', $alpha3CurrencyCodes)
            ->setDefault('description', null)->setAllowedTypes('description', ['null', 'string'])
            ->setDefault('metadata', [])->setAllowedTypes('metadata', ['array'])
        ;
    }
}
