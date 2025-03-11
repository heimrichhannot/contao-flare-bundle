<?php

namespace HeimrichHannot\FlareBundle\Exception;

class FilterException extends \Exception
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        protected ?string $method = null,
        protected ?string $source = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }
}