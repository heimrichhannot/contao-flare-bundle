<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent\Factory;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\List\ListSpec;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ValidationIntentFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListSpec $list): ValidationIntent
    {
        $jumpToReaderPageId = (int) ($contentModel->{ContentContainer::FIELD_JUMP_TO_READER}
            ?: ($list->config['jumpToReader'] ?? 0));
        $jumpToListViewPageId = (int) ($contentModel->{ContentContainer::FIELD_JUMP_TO_LISTVIEW}
            ?: ($list->config['jumpToListView'] ?? 0));

        $fieldAutoItem = $list->getAutoItemField();

        $intent = new ValidationIntent(
            jumpToReaderPageId: $jumpToReaderPageId,
            jumpToListViewPageId: $jumpToListViewPageId,
            autoItemField: $fieldAutoItem,
        );

        $violations = $this->validator->validate($intent);

        if ($violations->count()) {
            throw new ValidationFailedException($intent, $violations);
        }

        return $intent;
    }
}
