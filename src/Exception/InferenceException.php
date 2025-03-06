<?php

namespace HeimrichHannot\FlareBundle\Exception;

use Exception;

class InferenceException extends Exception
{
    public function __construct(
        string           $message,
        protected string $translationKey = '',
        protected array  $formatParams = [],
        int              $code = 0,
        Exception        $previous = null
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