<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent\Factory;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Exception\IntentException;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Sort\Factory\SortOrderSequenceFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class InteractiveIntentFactory
{
    public function __construct(
        private SortOrderSequenceFactory $sortOrderSequenceFactory,
        private ValidatorInterface       $validator,
    ) {}

    /**
     * @throws IntentException
     */
    public function createFromContent(ContentModel $contentModel, ListCompound $list): InteractiveIntent
    {
        $filterFormName = $contentModel->{ContentContainer::FIELD_FORM_NAME}
            ?: ('fl' . ($contentModel->id ?? ''));

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: (int) ($contentModel->{ContentContainer::FIELD_ITEMS_PER_PAGE} ?: 0),
        );

        $sortOrderSequence = $this->sortOrderSequenceFactory->createFromList($list);

        $jumpToReaderPageId = (int) ($contentModel->{ContentContainer::FIELD_JUMP_TO_READER}
            ?: ($list->config['jumpToReader'] ?? 0));

        $fieldAutoItem = $list->getAutoItemField();

        $intent = new InteractiveIntent(
            paginatorConfig: $paginatorConfig,
            formName: $filterFormName,
            sortOrderSequence: $sortOrderSequence,
            contentModelId: (int) $contentModel->id,
            formActionPageId: (int) $contentModel->{ContentContainer::FIELD_JUMP_TO},
            jumpToReaderPageId: $jumpToReaderPageId,
            autoItemField: $fieldAutoItem,
        );

        $violations = $this->validator->validate($intent);

        if ($violations->count()) {
            throw IntentException::fromViolations($intent, $violations, __METHOD__);
        }

        return $intent;
    }
}
