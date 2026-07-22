<?php

namespace IDCI\Bundle\PaymentBundle\Model;

use Flaky\Flaky;

class Transaction
{
    protected ?string $id = null;
    protected string $reference;
    protected ?int $number = null;
    protected string $paymentGatewayConfigurationAlias;
    protected ?string $paymentMethod = null;
    protected string $itemReference;
    protected ?string $customerReference = null;
    protected ?string $customerEmail = null;
    protected ?string $status = null;
    protected int $amount;
    protected string $currencyCode;
    protected ?string $description = null;
    protected array $metadata = [];
    protected array $notifications = [];
    protected ?\DateTime $createdAt = null;
    protected ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->reference = Flaky::id(62);
    }

    public function __toString(): string
    {
        return sprintf('%s - %s - %s - %d %s',
            $this->getId(),
            $this->getReference(),
            $this->getPaymentGatewayConfigurationAlias(),
            $this->getAmount(),
            $this->getCurrencyCode()
        );
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

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

    public function getPaymentGatewayConfigurationAlias(): string
    {
        return $this->paymentGatewayConfigurationAlias;
    }

    public function setPaymentGatewayConfigurationAlias(string $paymentGatewayConfigurationAlias): self
    {
        $this->paymentGatewayConfigurationAlias = $paymentGatewayConfigurationAlias;

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

    public function getItemReference(): ?string
    {
        return $this->itemReference;
    }

    public function setItemReference(string $itemReference): self
    {
        $this->itemReference = $itemReference;

        return $this;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function setCustomerReference(?string $customerReference): self
    {
        $this->customerReference = $customerReference;

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

    public function setStatus(?string $status): self
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

    public function getNotifications(): array
    {
        return $this->notifications;
    }

    public function getLastNotification(): ?TransactionNotification
    {
        $lastNotification = null;

        foreach ($this->getNotifications() as $notification) {
            if (null === $lastNotification
                || $lastNotification->getCreatedAt() < $notification->getCreatedAt()
            ) {
                $lastNotification = $notification;
            }
        }

        return $lastNotification;
    }

    public function addNotification(TransactionNotification $notification)
    {
        $notification->setTransaction($this);

        $this->notifications[] = $notification;
    }

    public function setNotifications(array $notifications): self
    {
        $this->notifications = $notifications;

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

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'reference' => $this->getReference(),
            'payment_gateway_configuration_alias' => $this->getPaymentGatewayConfigurationAlias(),
            'payment_method' => $this->getPaymentMethod(),
            'item_reference' => $this->getItemReference(),
            'customer_reference' => $this->getCustomerReference(),
            'customer_email' => $this->getCustomerEmail(),
            'status' => $this->getStatus(),
            'amount' => $this->getAmount(),
            'currency_code' => $this->getCurrencyCode(),
            'description' => $this->getDescription(),
            'metadata' => $this->getMetadata(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}
