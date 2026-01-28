<?php

namespace HeimrichHannot\FlareBundle\Context\Factory;

use Contao\ContentModel;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Context\InteractiveConfig;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InteractiveConfigFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListModel $listModel): InteractiveConfig
    {
        $filterFormName = $contentModel->flare_formName ?: 'fl' . $listModel->id;

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: (int) ($contentModel->flare_itemsPerPage ?: 0),
        );

        $sortDescriptor = $this->getSortDescriptor($listModel);

        $config = new InteractiveConfig(
            paginatorConfig: $paginatorConfig,
            sortDescriptor: $sortDescriptor,
            contentModelId: (int) $contentModel->id,
            formActionPage: (int) $contentModel->flare_jumpTo,
            formName: $filterFormName,
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