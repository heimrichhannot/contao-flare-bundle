<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use Contao\ContentModel;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InteractiveContextFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListModel $listModel): InteractiveContext
    {
        $filterFormName = $contentModel->flare_formName ?: 'fl' . $listModel->id;

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: (int) ($contentModel->flare_itemsPerPage ?: 0),
        );

        $sortDescriptor = $this->getSortDescriptor($listModel);

        $jumpToReaderPageId = (int) ($listModel->jumpToReader ?: $contentModel->flare_jumpToReader);

        $fieldAutoItem = DcaHelper::tryGetColumnName(
            $listModel->dc,
            $listModel->fieldAutoItem,
            DcaHelper::tryGetColumnName($listModel->dc, 'alias', 'id')
        );

        $config = new InteractiveContext(
            paginatorConfig: $paginatorConfig,
            sortDescriptor: $sortDescriptor,
            contentModelId: (int) $contentModel->id,
            formActionPage: (int) $contentModel->flare_jumpTo,
            formName: $filterFormName,
            jumpToReaderPageId: $jumpToReaderPageId,
            autoItemField: $fieldAutoItem,
        );

        $violations = $this->validator->validate($config);

        if ($violations->count()) {
            throw new ValidationFailedException($config, $violations);
        }

        return $config;
    }

    /**
     * Get the sort descriptor for a given list model.
     *
     * @return SortDescriptor|null The sort descriptor, or null if none is found.
     *
     * @throws FlareException bubbling from {@see SortDescriptor::fromSettings()}
     */
    private function getSortDescriptor(ListModel $listModel): ?SortDescriptor
    {
        if (!$listModel->sortSettings) {
            return null;
        }

        $sortSettings = StringUtil::deserialize($listModel->sortSettings);
        if (!$sortSettings || !\is_array($sortSettings)) {
            return null;
        }

        return SortDescriptor::fromSettings($sortSettings);
    }
}