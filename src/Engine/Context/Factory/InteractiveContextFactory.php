<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\Factory\SortDescriptorFactory;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class InteractiveContextFactory
{
    public function __construct(
        private SortDescriptorFactory $sortDescriptorFactory,
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListModel $listModel): InteractiveContext
    {
        $filterFormName = $contentModel->flare_formName ?: 'fl' . $listModel->id;

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: (int) ($contentModel->flare_itemsPerPage ?: 0),
        );

        $sortDescriptor = $this->sortDescriptorFactory->createFromListModel($listModel);

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
}