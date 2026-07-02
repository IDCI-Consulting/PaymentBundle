<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Flaky\Flaky;

class Transaction
{
    protected string $id;
    protected int $number;
    protected string $gatewayConfigurationAlias;
    protected string $paymentMethod;
    protected string $itemId;
    protected ?string $customerId;
    protected ?string $customerEmail;
    protected ?string $status;
    protected int $amount;
    protected string $currencyCode;
    protected ?string $description;
    protected array $metadata;
    protected array $raw;
    protected \DateTime $createdAt;
    protected \DateTime $updatedAt;
    protected bool $logged = true;

    public function __construct()
    {
        $this->id = Flaky::id(62);
    }

    public function __toString(): string
    {
        return $this->id;
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

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getGatewayConfigurationAlias(): string
    {
        return $this->gatewayConfigurationAlias;
    }

    public function setGatewayConfigurationAlias(string $gatewayConfigurationAlias): self
    {
        $this->gatewayConfigurationAlias = $gatewayConfigurationAlias;

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

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): self
    {
        $this->customerId = $customerId;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getRaw(): ?array
    {
        return $this->raw;
    }

    public function setRaw(?array $raw = []): self
    {
        $this->raw = $raw;

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isLogged(): bool
    {
        return $this->logged;
    }

    public function setLogged(bool $logged): self
    {
        $this->logged = $logged;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'gateway_configuration_alias' => $this->getGatewayConfigurationAlias(),
            'payment_method' => $this->getPaymentMethod(),
            'item_id' => $this->getItemId(),
            'customer_id' => $this->getCustomerId(),
            'customer_email' => $this->getCustomerEmail(),
            'status' => $this->getStatus(),
            'amount' => $this->getAmount(),
            'currency_code' => $this->getCurrencyCode(),
            'description' => $this->getDescription(),
            'metadata' => $this->getMetadata(),
            'raw' => $this->getRaw(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
