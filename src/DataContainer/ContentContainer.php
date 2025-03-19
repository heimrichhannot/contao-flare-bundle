<?php

namespace HeimrichHannot\FlareBundle\DataContainer;

class ContentContainer
{
    public const FIELD_FORM_NAME = 'flare_formName';
    public const FIELD_LIST = 'flare_list';
    public const FIELD_ITEMS_PER_PAGE = 'flare_itemsPerPage';
    public const FIELD_JUMP_TO = 'flare_jumpTo';
    public const TABLE_NAME = 'tl_content';

    /*public function __construct(
        private readonly TemplateManager $templateManager
    ) {}*/

    /*#[AsCallback(self::TABLE_NAME, 'fields.' . self::FIELD_ERROR_TEMPLATE . '.options')]
    public function getErrorTemplateOptions(): array
    {
        return $this->templateManager->makeFinder('flare/error')->asTemplateOptions() ?? [];
    }*/
}