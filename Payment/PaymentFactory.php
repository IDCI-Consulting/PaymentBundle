<?php

namespace IDCI\Bundle\PaymentBundle\Payment;

use IDCI\Bundle\PaymentBundle\Entity\Payment;
use Payum\ISO4217\ISO4217;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentFactory
{
    public function create(array $parameters): Payment
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);
        $resolvedParameters = $resolver->resolve($parameters);

        return (new Payment())
            ->setItemId($resolvedParameters['item_id'])
            ->setCustomerId($resolvedParameters['customer_id'])
            ->setCustomerEmail($resolvedParameters['customer_email'])
            ->setStatus(Payment::STATUS_CREATED)
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
                'customer_id' => null,
                'customer_email' => null,
                'description' => null,
            ])
            ->setAllowedTypes('item_id', ['int', 'string'])
            ->setAllowedTypes('amount', ['int', 'double'])
            ->setAllowedTypes('currency_code', 'string')
            ->setAllowedTypes('customer_id', ['null', 'int', 'string'])
            ->setAllowedTypes('customer_email', ['null', 'string'])
            ->setAllowedTypes('description', ['null', 'string'])
            ->setAllowedValues('currency_code', $alpha3CurrencyCodes)
        ;
    }
}
