<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Flaky\Flaky;
use PascalDeVink\ShortUuid\ShortUuid;
use Ramsey\Uuid\Uuid;

class Transaction
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $gatewayConfigurationAlias;

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
    protected $metadatas;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime
     */
    protected $updatedAt;

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
            'gatewayConfigurationAlias' => $this->gatewayConfigurationAlias,
            'itemId' => $this->getItemId(),
            'customerId' => $this->customerId,
            'customerEmail' => $this->customerEmail,
            'status' => $this->status,
            'amount' => $this->amount,
            'currencyCode' => $this->currencyCode,
            'description' => $this->description,
            'metadatas' => $this->metadatas,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    public function getShorterItemId(): ?string
    {
        if (36 !== strlen($this->itemId)) {
            return $this->itemId;
        }

        return (new ShortUuid())->encode(Uuid::fromString($this->itemId));
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
        return isset($this->metadatas[$key]);
    }

    public function getMetadata(string $key)
    {
        return $this->metadatas[$key];
    }

    public function addMetadata(string $key, $value)
    {
        $this->metadatas[$key] = $value;
    }

    public function getMetadatas(): ?array
    {
        return $this->metadatas;
    }

    public function setMetadatas(array $metadatas): self
    {
        $this->metadatas = [];

        foreach ($metadatas as $key => $value) {
            $this->addMetadata($key, $value);
        }

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
}
