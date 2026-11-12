<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent\Factory;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\List\ListCompound;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ValidationIntentFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListCompound $list): ValidationIntent
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

    /**
     * The reader's rules for links out of a list: its reader page and auto-item field, no filter values.
     */
    public function createFromIntent(InteractiveIntent $intent): ValidationIntent
    {
        return new ValidationIntent(
            jumpToReaderPageId: $intent->jumpToReaderPageId,
            autoItemField: $intent->autoItemField,
        );
    }
}
