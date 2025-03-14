<?php

namespace HeimrichHannot\FlareBundle\Exception;

class InferenceException extends FlareException
{
    public function __construct(
        string           $message,
        protected string $translationKey = '',
        protected array  $formatParams = [],
        int              $code = 0,
        \Throwable       $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }

    public function getFormatParams(): array
    {
        return $this->formatParams;
    }
}