<?php

namespace IDCI\Bundle\PaymentBundle\System;

use IDCI\Bundle\PaymentBundle\Event\TransactionEvent;
use IDCI\Bundle\PaymentBundle\Manager\TransactionManagerInterface;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

abstract class AbstractPaymentSystem implements PaymentSystemInterface
{
    public const TRANSACTION_REFERENCE_QUERY_PARAMETER = 'transaction_reference';

    protected Environment $templating;
    protected EventDispatcherInterface $eventDispatcher;
    protected TransactionManagerInterface $transactionManager;
    protected UrlGeneratorInterface $urlGenerator;

    public function __construct(
        Environment $templating,
        EventDispatcherInterface $eventDispatcher,
        TransactionManagerInterface $transactionManager,
        UrlGeneratorInterface $urlGenerator,
    ) {
        $this->templating = $templating;
        $this->eventDispatcher = $eventDispatcher;
        $this->transactionManager = $transactionManager;
        $this->urlGenerator = $urlGenerator;
    }

    private function resolveParameters(array $parameters): array
    {
        $resolver = new OptionsResolver();
        $this->configureParameters($resolver);

        return $resolver->resolve($parameters);
    }

    public function configureParameters(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('transaction')->setAllowedTypes('transaction', [Transaction::class])
            ->setRequired('request')->setAllowedTypes('request', [Request::class])
            ->setDefault('client_return_url', null)->setAllowedTypes('client_return_url', ['null', 'string'])
                ->setNormalizer('client_return_url', function (Options $options, $value) {
                    if (null !== $value) {
                        return $value;
                    }

                    return $this->urlGenerator->generate(
                        $options['request']->attributes->get('_route'),
                        array_merge(
                            $options['request']->attributes->get('_route_params'),
                            [
                                'transaction_reference' => $options['transaction']->getReference(),
                            ]
                        ),
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                })
            ->setDefault('system_notification_url', null)->setAllowedTypes('system_notification_url', ['null', 'string'])
                ->setNormalizer('system_notification_url', function (Options $options, $value) {
                    if (null !== $value) {
                        return $value;
                    }

                    return $this->urlGenerator->generate(
                        'idci_payment_gateway_transaction_notification',
                        [
                            'configuration_alias' => $options['transaction']->getPaymentGatewayConfigurationAlias(),
                        ],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );
                })
        ;
    }

    public function isReturnClientRequest(Request $request): bool
    {
        return $request->query->has(self::TRANSACTION_REFERENCE_QUERY_PARAMETER);
    }

    public function initializeTransaction(Transaction $transaction, Request $request): Transaction
    {
        $initializedTransaction = null;

        if (null !== $transaction->getId()) {
            $initializedTransaction = $this->transactionManager->retrieveTransactionById($transaction->getId());
        } elseif (null !== $transaction->getReference()) {
            $initializedTransaction = $this->transactionManager->retrieveTransactionByReference($transaction->getReference());
        }

        if (null === $initializedTransaction) {
            $initializedTransaction = $transaction;
            $this->eventDispatcher->dispatch(new TransactionEvent($initializedTransaction), TransactionEvent::CREATED);
        }

        return $initializedTransaction;
    }

    public function retrieveTransaction(Request $request): ?Transaction
    {
        $transactionReference = $request->query->get(self::TRANSACTION_REFERENCE_QUERY_PARAMETER);

        if (null !== $transactionReference) {
            return $this->transactionManager->retrieveTransactionByReference($transactionReference);
        }

        return $this->doRetrieveTransaction($request);
    }

    public function processTransaction(Transaction $transaction, Request $request, array $parameters): ProcessedTransactionResult
    {
        $resolvedParameters = $this->resolveParameters(array_merge(
            [
                'transaction' => $transaction,
                'request' => $request,
            ],
            $parameters
        ));

        if ($this->isReturnClientRequest($request)) {
            return $this->processReturnClientTransaction($resolvedParameters);
        }

        return $this->processInitialTransaction($resolvedParameters);
    }

    public function processInitialTransaction(array $parameters): ProcessedTransactionResult
    {
       return $this->doProcessInitialTransaction($parameters);
    }

    public function processReturnClientTransaction(array $parameters): ProcessedTransactionResult
    {
        $processedTransactionResult = $this->doProcessReturnClientTransaction($parameters);

        if (null !== $processedTransactionResult) {
            return $processedTransactionResult;
        }

        return new ProcessedTransactionResult(
            ProcessedTransactionResult::TYPE_HTML,
            $this->templating->render(sprintf('@IDCIPayment/TransactionStatus/%s.html.twig', $parameters['transaction']->getStatus()), [
                'transaction' => $parameters['transaction'],
            ])
        );
    }

    public function handleNotification(Transaction $transaction, Request $request, array $parameters): void
    {
        $this->doHandleNotification($this->resolveParameters(array_merge(
            [
                'transaction' => $transaction,
                'request' => $request,
            ],
            $parameters
        )));
    }

    abstract protected function doRetrieveTransaction(Request $request): ?Transaction;
    abstract protected function doProcessInitialTransaction(array $parameters): ProcessedTransactionResult;
    abstract protected function doProcessReturnClientTransaction(array $parameters): ?ProcessedTransactionResult;
    abstract protected function doHandleNotification(array $parameters): void;
}