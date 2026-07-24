<?php

namespace IDCI\Bundle\PaymentBundle\Model;

class TransactionNotification
{
    protected ?string $id = null;
    protected string $state;
    protected string $message;
    protected array $metadata;
    protected Transaction $transaction;
    protected \DateTime $createdAt;

    public function __toString(): string
    {
        return sprintf('%s - %s', $this->getId(), $this->getState());
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function hasMetadata(string $key)
    {
        return isset($this->metadata[$key]);
    }

    public function getMetadata(?string $key = null)
    {
        if (null === $key) {
            return $this->metadata;
        }

        return $this->hasMetadata($key) ? $this->metadata[$key] : null;
    }

    public function addMetadata(string $key, $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = [];

        foreach ($metadata as $key => $value) {
            $this->addMetadata($key, $value);
        }

        return $this;
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}