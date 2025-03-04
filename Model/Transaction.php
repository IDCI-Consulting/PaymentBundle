<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Flaky\Flaky;

class Transaction
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $number;

    /**
     * @var string
     */
    protected $gatewayConfigurationAlias;

    /**
     * @var string
     */
    protected $paymentMethod;

    /**
     * @var string
     */
    protected $itemId;

    /**
     * @var string|null
     */
    protected $customerId;

    /**
     * @var string|null
     */
    protected $customerEmail;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var int
     */
    protected $amount;

    /**
     * @var string
     */
    protected $currencyCode;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @var array
     */
    protected $raw;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

    /**
     * @var bool
     */
    protected $logged = true;

    public function __construct()
    {
        $this->id = Flaky::id(62);
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gateway_configuration_alias' => $this->gatewayConfigurationAlias,
            'payment_method' => $this->paymentMethod,
            'item_id' => $this->itemId,
            'customer_id' => $this->customerId,
            'customer_email' => $this->customerEmail,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency_code' => $this->currencyCode,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'raw' => $this->raw,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
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

    public function setAmount(string $amount): self
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

    public function addMetadata(string $key, $value)
    {
        $this->metadata[$key] = $value;
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
}
