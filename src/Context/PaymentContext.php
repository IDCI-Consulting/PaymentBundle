<?php

namespace IDCI\Bundle\PaymentBundle\Context;

use IDCI\Bundle\PaymentBundle\Factory\TransactionFactory;
use IDCI\Bundle\PaymentBundle\Gateway\PaymentGateway;
use IDCI\Bundle\PaymentBundle\Model\ProcessedTransactionResult;
use IDCI\Bundle\PaymentBundle\Model\Transaction;
use IDCI\Bundle\PaymentBundle\System\PaymentSystemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentContext
{
    public const TRANSACTION_REFERENCE_QUERY_PARAMETER = 'transaction_reference';

    private LoggerInterface $logger;
    private Request $request;
    private PaymentGateway $paymentGateway;
    private Transaction $transaction;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    public function getPaymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    public function setPaymentGateway(PaymentGateway $paymentGateway): self
    {
        $this->paymentGateway = $paymentGateway;

        return $this;
    }

    public function getPaymentSystem(): PaymentSystemInterface
    {
        return $this->getPaymentGateway()->getPaymentSystem();
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }
    public function isReturnClientRequest(): bool
    {
        return $this->getPaymentSystem()->isReturnClientRequest($this->getRequest());
    }

    public function initializeTransaction(array $transactionData, array $parameters = []): void
    {
        $transaction = $this->getPaymentSystem()->initializeTransaction(TransactionFactory::getInstance()->create($transactionData), $this->getRequest());
        $this->getLogger()->info('[IDCIPaymentBundle] Initialize transaction', [
            'class' => self::class,
            'transaction' => $transaction,
        ]);
        $this->setTransaction($transaction);
    }

    public function retrieveTransaction(): void
    {
        $transaction = $this->getPaymentSystem()->retrieveTransaction($this->getRequest());
        $this->getLogger()->info('[IDCIPaymentBundle] retrieve transaction', [
            'class' => self::class,
            'transaction' => $transaction,
        ]);
        $this->setTransaction($transaction);
    }

    public function processTransaction(array $parameters = []): ProcessedTransactionResult
    {
        $this->getLogger()->info('[IDCIPaymentBundle] process transaction', [
            'class' => self::class,
            'transaction' => $this->getTransaction(),
        ]);

        try {
            return $this->getPaymentSystem()->processTransaction(
                $this->getTransaction(),
                $this->getRequest(),
                $this->mergeParameters($parameters),
            );
        } catch (\Exception $e) {
            $this->getLogger()->error(sprintf('[IDCIPaymentBundle] fail to process transaction: %s', $e->getMessage()), [
                'class' => self::class,
                'transaction' => $this->getTransaction(),
            ]);

            return new ProcessedTransactionResult(
                ProcessedTransactionResult::TYPE_HTML,
                sprintf('ERROR: %s', $e->getMessage()),
            );
        }
    }

    private function mergeParameters(array $parameters): array
    {
        return array_merge(
            $this->getPaymentGateway()->getParameters(),
            $parameters
        );
    }
}