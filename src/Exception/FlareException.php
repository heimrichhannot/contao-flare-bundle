<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

/**
 * @internal This exception is thrown internally and should not be
 *           thrown by userland code. You may catch it, however.
 */
class FlareException extends \Exception
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