<?php

namespace IDCI\Bundle\PaymentBundle\Model;

class ProcessedTransactionResult
{
    public const TYPE_HTML = 'html';
    public const TYPE_REDIRECTION = 'redirection';

    public const AVAILABLE_TYPES = [
        self::TYPE_HTML,
        self::TYPE_REDIRECTION,
    ];

    private string $type;
    private string $content;

    public function __construct(string $type, string $content)
    {
        $this
            ->setType($type)
            ->setContent($content)
        ;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::AVAILABLE_TYPES)) {
            throw new \UnexpectedValueException(sprintf('Given type "%s" is not valid', $type));
        }

        $this->type = $type;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }
}