<?php

namespace IDCI\Bundle\PaymentBundle\Entity;

class Transaction
{
    const STATUS_CREATED = 'created';

    const STATUS_VALIDATED = 'validated';

    const STATUS_CANCELED = 'canceled';

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $gatewayConfigurationAlias;

    /**
     * @var string
     */
    private $itemId;

    /**
     * @var string|null
     */
    private $customerId;

    /**
     * @var string|null
     */
    private $customerEmail;

    /**
     * @var string
     */
    private $status;

    /**
     * @var int
     */
    private $amount;

    /**
     * @var string
     */
    private $currencyCode;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    public function __get($name)
    {
        $method = sprintf('get%s', ucfirst($name));

        return $this->$method();
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function onPrePersist()
    {
        $now = new \DateTime('now');

        $this
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
    }

    public function onPreUpdate()
    {
        $this->setUpdatedAt(new \DateTime('now'));
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
