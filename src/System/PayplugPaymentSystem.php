<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Context\TransactionStatus;
use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\Model\TransactionNotification;
use Payplug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Countries;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PayplugPaymentSystem extends AbstractPaymentSystem
{
    public const MODE_HOSTED = 'hosted';
    public const MODE_LIGHTBOX = 'lightbox';
    public const MODE_INTEGRATED = 'integrated';

    public function configureParameters(OptionsResolver $resolver): void
    {
        parent::configureParameters($resolver);

        $resolver
            ->setDefined('version')->setAllowedTypes('version', ['string', 'null'])
            ->setRequired('secret_key')->setAllowedTypes('secret_key', ['string'])
            ->setRequired('mode')->setAllowedTypes('mode', ['string'])
            ->setDefault('payment_data', function (OptionsResolver $paymentDataResolver, Options $options) {
                $transaction = $options['transaction'];
                $request = $options['request'];
                $mode = $options['mode'];
                $clientReturnUrl = $options['client_return_url'];
                $systemNotificationUrl = $options['system_notification_url'];

                if (null === $transaction) {
                    return [];
                }

                $paymentDataResolver
                    ->setDefault('amount', $transaction->getAmount())->setAllowedTypes('amount', ['int', 'float'])
                    ->setDefault('currency', $transaction->getCurrencyCode())->setAllowedTypes('currency', ['string'])
                    ->setDefault('billing', function (OptionsResolver $billingResolver) {
                        $billingResolver
                            ->setDefined('title')->setAllowedTypes('title', ['string'])
                            ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                            ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                            ->setDefined('mobile_phone_number')->setAllowedTypes('mobile_phone_number', ['string'])
                            ->setDefined('email')->setAllowedTypes('email', ['string'])
                                ->setNormalizer('email', function (OptionsResolver $emailResolver, $value) {
                                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                        throw new InvalidOptionException('The option "billing[email]" is not a valid email');
                                    }

                                    return $value;
                                })
                            ->setDefined('address1')->setAllowedTypes('address1', ['string'])
                            ->setDefined('postcode')->setAllowedTypes('postcode', ['string'])
                            ->setDefined('city')->setAllowedTypes('city', ['string'])
                            ->setDefined('country')->setAllowedValues('country', Countries::getCountryCodes())
                            ->setDefined('language')->setAllowedTypes('language', ['string'])
                        ;
                    })
                    ->setDefault('shipping', function (OptionsResolver $shippingResolver) {
                        $shippingResolver
                            ->setDefined('title')->setAllowedTypes('title', ['string'])
                            ->setDefined('first_name')->setAllowedTypes('first_name', ['string'])
                            ->setDefined('last_name')->setAllowedTypes('last_name', ['string'])
                            ->setDefined('mobile_phone_number')->setAllowedTypes('mobile_phone_number', ['string'])
                            ->setDefined('email')->setAllowedTypes('email', ['string'])
                                ->setNormalizer('email', function (OptionsResolver $emailResolver, $value) {
                                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                        throw new InvalidOptionException('The option "shipping[email]" is not a valid email');
                                    }

                                    return $value;
                                })
                            ->setDefined('address1')->setAllowedTypes('address1', ['string'])
                            ->setDefined('postcode')->setAllowedTypes('postcode', ['string'])
                            ->setDefined('city')->setAllowedTypes('city', ['string'])
                            ->setDefined('country')->setAllowedValues('country', Countries::getCountryCodes())
                            ->setDefined('language')->setAllowedTypes('language', ['string'])
                        ;
                    })
                    ->setDefault('hosted_payment', function (OptionsResolver $hostedPaymentResolver) use ($request, $transaction, $mode, $clientReturnUrl) {
                        if (!in_array($mode, [self::MODE_HOSTED, self::MODE_LIGHTBOX])) {
                            return;
                        }

                        $hostedPaymentResolver
                            ->setDefault('return_url', $clientReturnUrl)->setAllowedTypes('return_url', ['string'])
                            ->setDefault('cancel_url', null)->setAllowedTypes('cancel_url', ['null', 'string'])
                                ->setNormalizer('cancel_url', function (Options $options, $value) use ($request, $transaction) {
                                    if (null !== $value) {
                                        return $value;
                                    }

                                    return $this->urlGenerator->generate(
                                        $request->attributes->get('_route'),
                                        array_merge(
                                            $request->attributes->get('_route_params'),
                                            [
                                                self::TRANSACTION_REFERENCE_QUERY_PARAMETER => $transaction->getReference(),
                                                'cancel' => true,
                                            ]
                                        ),
                                        UrlGeneratorInterface::ABSOLUTE_URL
                                    );
                                })
                            ->setDefined('sent_by')->setAllowedTypes('sent_by', ['string'])
                        ;
                    })
                    ->setDefault('notification_url', $systemNotificationUrl)->setAllowedTypes('notification_url', ['string'])
                    ->setDefined('payment_method')->setAllowedTypes('payment_method', ['string'])
                    ->setDefault('metadata', [])->setAllowedTypes('metadata', ['array'])
                        ->setNormalizer('metadata', function (Options $options, $value) use ($transaction) {
                            return array_merge(['transaction_id' => $transaction->getId()], $value);
                        })
                ;
            })
        ;
    }

    protected function doRetrieveTransaction(Request $request, array $parameters): ?Transaction
    {
        if (Request::METHOD_POST !== $request->getMethod()) {
            return null;
        }

        $payload = json_decode($request->getContent(), true);

        if ('payment' !== $payload['object']) {
            return null;
        }

        Payplug\Payplug::init(array(
            'apiVersion' => $parameters['version'],
            'secretKey' => $parameters['secret_key'],
        ));

        $payplugPayment = \Payplug\Payment::retrieve($payload['id']);

        return $this->transactionManager->retrieveTransactionById($payplugPayment->metadata['transaction_id']);
    }

    protected function doProcessInitialTransaction(array $parameters): ProcessedTransactionResult
    {
        $transaction = $parameters['transaction'];

        Payplug\Payplug::init(array(
            'apiVersion' => $parameters['version'],
            'secretKey' => $parameters['secret_key'],
        ));

        $payplugPayment = Payplug\Payment::create($parameters['payment_data']);

        $transaction
            ->setStatus(TransactionStatus::STATUS_CREATED)
            ->addMetadata('payment_id', $payplugPayment->id)
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($parameters['payment_data']))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_TRANSMIT)
                    ->setState('CREATED')
                    ->setCreatedAt(new \DateTime('now'))
            )
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState('CREATED')
                    ->setCreatedAt(new \DateTime('now'))
            )
        ;

        $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return new ProcessedTransactionResult(
            ProcessedTransactionResult::TYPE_REDIRECTION,
            $payplugPayment->hosted_payment->payment_url
        );
    }

    protected function doProcessReturnClientTransaction(array $parameters): ?ProcessedTransactionResult
    {
        $transaction = $parameters['transaction'];
        $request = $parameters['request'];

        if (
            null === $transaction->getMetadata('payment_id')
            || $transaction->getStatus() !== TransactionStatus::STATUS_CREATED
        ) {
            return null;
        }

        $transaction
            ->setStatus(TransactionStatus::STATUS_PENDING)
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($request->query->all()))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState('CLIENT_RETURN_REQUEST')
                    ->setCreatedAt(new \DateTime('now'))
            )
        ;

        Payplug\Payplug::init(array(
            'apiVersion' => $parameters['version'],
            'secretKey' => $parameters['secret_key'],
        ));

        $payplugPayment = \Payplug\Payment::retrieve($transaction->getMetadata('payment_id'));

        if (null !== $payplugPayment->failure && 'canceled' === $payplugPayment->failure->code) {
            $transaction
                ->setStatus(TransactionStatus::STATUS_CANCELED)
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                        ->setState($payplugPayment->failure->code)
                        ->setCreatedAt(new \DateTime('now'))
                )
            ;
        }

        if (
            true === $payplugPayment->is_paid
            && $transaction->getAmount() === $payplugPayment->amount
            && $transaction->getCurrencyCode() === $payplugPayment->currency
        ) {
            $transaction
                ->setStatus(TransactionStatus::STATUS_APPROVED)
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                        ->setState('PAID')
                        ->setCreatedAt(new \DateTime('now'))
                )
            ;
        }

        $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return null;
    }

    protected function doHandleNotification(array $parameters): void
    {
        $transaction = $parameters['transaction'];
        $request = $parameters['request'];

        if (null === $transaction->getMetadata('payment_id')) {
            return;
        }

        $transaction
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage($request->getContent())
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState('NOTIFY')
                    ->setCreatedAt(new \DateTime('now'))
            )
        ;

        Payplug\Payplug::init(array(
            'apiVersion' => $parameters['version'],
            'secretKey' => $parameters['secret_key'],
        ));

        $payplugPayment = \Payplug\Payment::retrieve($transaction->getMetadata('payment_id'));

        if (null !== $payplugPayment->failure && 'canceled' === $payplugPayment->failure->code) {
            $transaction
                ->setStatus(TransactionStatus::STATUS_CANCELED)
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                        ->setState($payplugPayment->failure->code)
                        ->setCreatedAt(new \DateTime('now'))
                )
            ;

            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            return;
        }

        if (
            true === $payplugPayment->is_paid
            && $transaction->getAmount() === $payplugPayment->amount
            && $transaction->getCurrencyCode() === $payplugPayment->currency
        ) {
            $transaction
                ->setStatus(TransactionStatus::STATUS_APPROVED)
                ->addNotification(
                    (new TransactionNotification())
                        ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                        ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                        ->setState('PAID')
                        ->setCreatedAt(new \DateTime('now'))
                )
            ;

            $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

            return;
        }

        $transaction
            ->setStatus(TransactionStatus::STATUS_FAILED)
            ->addNotification(
                (new TransactionNotification())
                    ->setMessage(json_encode($this->transformPaymentToArray($payplugPayment)))
                    ->setCallDirection(TransactionNotification::CALL_DIRECTION_RECEIVE)
                    ->setState('NOT PAID')
                    ->setCreatedAt(new \DateTime('now'))
            )
        ;

        $this->eventDispatcher->dispatch(new TransactionEvent($transaction), TransactionEvent::UPDATED);

        return;
    }

    public static function transformPaymentToArray(\Payplug\Resource\Payment $payment): array
    {
        $reflectionClass = new \ReflectionClass($payment);
        $method = $reflectionClass->getMethod('getAttributes');
        $method->setAccessible(true);

        $paymentArray = $method->invoke($payment);
        foreach ($paymentArray as $key => $value) {
            if (!is_object($value)) {
                continue;
            }

            $paymentArray[$key] = $method->invoke($value);
        }

        return $paymentArray;
    }
}