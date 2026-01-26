<?php

namespace HeimrichHannot\FlareBundle\Dto;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Random\RandomException;

class ContentContext
{
    public const CONTEXT_LIST = 'list';
    public const CONTEXT_READER = 'reader';
    public const CONTEXT_TWIG = 'twig';

    private string $uniqueId;

    public function __construct(
        private string           $context,
        private ?ContentModel    $contentModel = null,
        private ?string          $formName = null,
        private ?int             $actionPage = null,
        private ?PaginatorConfig $paginatorConfig = null,
        private ?SortDescriptor  $sortDescriptor = null,
    ) {}

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getContentModel(): ?ContentModel
    {
        return $this->contentModel;
    }

    public function setContentModel(?ContentModel $contentModel): static
    {
        $this->contentModel = $contentModel;
        return $this;
    }

    public function getFormName(): ?string
    {
        return $this->formName;
    }

    public function setFormName(?string $formName): static
    {
        $this->formName = $formName;
        return $this;
    }

    public function getActionPage(): ?int
    {
        return $this->actionPage;
    }

    public function setActionPage(?int $actionPage): static
    {
        $this->actionPage = $actionPage;
        return $this;
    }

    /** @deprecated  */
    public function getPaginatorConfig(): PaginatorConfig
    {
        return $this->paginatorConfig ?? new PaginatorConfig();
    }

    /** @deprecated  */
    public function setPaginatorConfig(?PaginatorConfig $paginatorConfig): static
    {
        $this->paginatorConfig = $paginatorConfig;
        return $this;
    }

    /** @deprecated  */
    public function getSortDescriptor(): ?SortDescriptor
    {
        return $this->sortDescriptor;
    }

    /** @deprecated */
    public function setSortDescriptor(?SortDescriptor $sortDescriptor): static
    {
        $this->sortDescriptor = $sortDescriptor;
        return $this;
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

    public static function create(string $context): static
    {
        return new static($context);
    }
}