<?php

namespace IDCI\Bundle\PaymentBundle\Model;

class GatewayResponse
{
    /**
     * @var string
     */
    private $transactionUuid;

    /**
     * @var string
     */
    private $paymentMethod;

    /**
     * @var int
     */
    private $amount;

    /**
     * @var string
     */
    private $currencyCode;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $message;

    /**
     * @var \DateTime
     */
    private $date;

    /**
     * @var string
     */
    private $raw;

    public function toArray(): array
    {
        return [
            'transaction_uuid' => $this->getTransactionUuid(),
            'amount' => $this->getAmount(),
            'status' => $this->getStatus(),
            'message' => $this->getMessage(),
            'raw' => $this->getRaw(),
        ];
    }

    public function getTransactionUuid(): ?string
    {
        return $this->transactionUuid;
    }

    public function setTransactionUuid(string $transactionUuid): self
    {
        $this->transactionUuid = $transactionUuid;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getRaw(): ?array
    {
        return $this->raw;
    }

    public function setRaw(array $raw): self
    {
        $this->raw = $raw;

        return $this;
    }
}
