<?php

namespace IDCI\Bundle\PaymentBundle\Factory;

use Flaky\Flaky;
use IDCI\Bundle\PaymentBundle\Context\TransactionStatus;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Model\TransactionNotification;
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

        $transaction = (new Transaction())
            ->setId($resolvedData['id'])
            ->setReference($resolvedData['reference'])
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

        foreach ($resolvedData['notification_histories'] as $notificationData) {
            $transaction->addNotification(self::createTransactionNotification($notificationData));
        }

        return $transaction;
    }

    public function createTransactionNotification(array $data): TransactionNotification
    {
        return (new TransactionNotification())
            ->setId($data['id'])
            ->setCallDirection($data['call_direction'])
            ->setState($data['state'])
            ->setMessage($data['message'])
            ->setMetadata($data['metadata'])
            ->setCreatedAt(new \DateTime($data['created_at']))
        ;
    }

    public function configure(OptionsResolver $resolver): void
    {
        $currencies = (new ISO4217())->findAll();

        $alpha3CurrencyCodes = array_map(function ($currency) {
            return $currency->getAlpha3();
        }, $currencies);

        $resolver
            ->setDefault('id', null)->setAllowedTypes('id', ['null', 'string'])
            ->setRequired('reference')->setAllowedTypes('reference', ['string'])
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
            ->setDefault('notification_histories', [])->setAllowedTypes('notification_histories', ['array'])
                ->setNormalizer('notification_histories', function(Options $options, $value): array {
                    $notificationHistoryResolver = new OptionsResolver();

                    $notificationHistoryResolver
                        ->setRequired('id')->setAllowedTypes('id', ['string'])
                        ->setRequired('call_direction')->setAllowedValues('call_direction', TransactionNotification::AVAILABLE_CALL_DIRECTIONS)
                        ->setRequired('state')->setAllowedTypes('state', ['string'])
                        ->setRequired('message')->setAllowedTypes('message', ['string'])
                        ->setDefined('metadata')->setAllowedTypes('metadata', ['null', 'array'])
                        ->setRequired('created_at')->setAllowedTypes('created_at', ['string', \DateTime::class])
                            ->setNormalizer('created_at', function(Options $options, $value): \DateTime {
                                if (is_string($value)) {
                                    return new \DateTime($value);
                                }

                                return $value;
                            })
                    ;

                    foreach ($value as $notificationHistoryData) {
                        $notificationHistoryResolver->resolve($notificationHistoryData);
                    }

                    return $value;
                })
        ;
    }
}
