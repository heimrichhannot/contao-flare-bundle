<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Contao\ContentModel;
use Random\RandomException;

class ContentContext
{
    public const CONTEXT_LIST = 'list';
    public const CONTEXT_READER = 'reader';
    public const CONTEXT_TWIG = 'twig';

    private string $uniqueId;

    public function __construct(
        private readonly string        $context,
        private readonly ?ContentModel $contentModel = null,
        private readonly ?string       $formName = null,
        private readonly ?int          $actionPage = null,
    ) {}

    public function getContext(): string
    {
        return $this->context;
    }

    public function getContentModel(): ?ContentModel
    {
        return $this->contentModel;
    }

    public function getFormName(): ?string
    {
        return $this->formName;
    }

    public function getActionPage(): ?int
    {
        return $this->actionPage;
    }

    public function getUniqueId(): string
    {
        if (isset($this->uniqueId)) {
            return $this->uniqueId;
        }

        if ($contentModel = $this->getContentModel()) {
            return $this->uniqueId = 'context.' . $this->getContext() . ';' . $contentModel::class . '.' . $contentModel->id;
        }

        try {
            $random = \bin2hex(\random_bytes(16));
        } catch (RandomException) {
            $random = \md5(\uniqid('', true));
        }

        return $this->uniqueId = 'context.' . $this->getContext() . ';flare.' . $random;
    }

    /**
     * Checks if the context is a list.
     * @api
     */
    public function isList(): bool
    {
        return $this->getContext() === self::CONTEXT_LIST;
    }

    /**
     * Checks if the context is a reader.
     * @api
     */
    public function isReader(): bool
    {
        return $this->getContext() === self::CONTEXT_READER;
    }

    /**
     * Checks if the context is a twig template.
     * @api
     */
    public function isTwig(): bool
    {
        return $this->getContext() === self::CONTEXT_TWIG;
    }
}