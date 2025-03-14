<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use HeimrichHannot\FlareBundle\Manager\TemplateManager;

class ContentContainer
{
    public const FIELD_ERROR_TEMPLATE = 'flare_errorTpl';
    public const FIELD_FORM_NAME = 'flare_formName';
    public const FIELD_FORM_TEMPLATE = 'flare_formTpl';
    public const FIELD_LIST = 'flare_list';
    public const FIELD_LIST_TEMPLATE = 'flare_listTpl';
    public const TABLE_NAME = 'tl_content';

    public function __construct(
        private readonly TemplateManager $templateManager
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.' . self::FIELD_ERROR_TEMPLATE . '.options')]
    public function getErrorTemplateOptions(): array
    {
        return $this->templateManager->getTemplateFinder('flare/error')->asTemplateOptions() ?? [];
    }

    #[AsCallback(self::TABLE_NAME, 'fields.' . self::FIELD_FORM_TEMPLATE . '.options')]
    public function getFormTemplateOptions(): array
    {
        return $this->templateManager->getTemplateFinder('flare/form')->asTemplateOptions() ?? [];
    }

    #[AsCallback(self::TABLE_NAME, 'fields.' . self::FIELD_LIST_TEMPLATE . '.options')]
    public function getListTemplateOptions(): array
    {
        return $this->templateManager->getTemplateFinder('flare/list')->asTemplateOptions() ?? [];
    }
}