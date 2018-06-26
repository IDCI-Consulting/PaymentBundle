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
            'transaction_uuid' => $this->transactionUuid,
            'status' => $status,
            'message' => $message,
            'raw' => $raw,
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

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw($raw): self
    {
        if (is_array($raw)) {
            $raw = json_encode($raw);
        }

        $this->raw = $raw;

        return $this;
    }
}
