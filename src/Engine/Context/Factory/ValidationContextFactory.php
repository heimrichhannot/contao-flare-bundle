<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\Lists\ListSpec;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ValidationContextFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromContent(ContentModel $contentModel, ListSpec $list): ValidationContext
    {
        $jumpToReaderPageId = (int) ($contentModel->{ContentContainer::FIELD_JUMP_TO_READER}
            ?: ($list->config['jumpToReader'] ?? 0));
        $jumpToListViewPageId = (int) ($contentModel->{ContentContainer::FIELD_JUMP_TO_LISTVIEW}
            ?: ($list->config['jumpToListView'] ?? 0));

        $fieldAutoItem = $list->getAutoItemField();

        $config = new ValidationContext(
            jumpToReaderPageId: $jumpToReaderPageId,
            jumpToListViewPageId: $jumpToListViewPageId,
            autoItemField: $fieldAutoItem,
        );

        $violations = $this->validator->validate($config);

        if ($violations->count()) {
            throw new ValidationFailedException($config, $violations);
        }

        return $config;
    }

    public function createFromInteractiveView(InteractiveView $interactiveView): ValidationContext
    {
        $config = new ValidationContext(
            entryCache: static fn (): ?array => $interactiveView->issetEntries()
                    ? $interactiveView->getEntries()
                    : null,
        );

        $violations = $this->validator->validate($config);

        if ($violations->count()) {
            throw new ValidationFailedException($config, $violations);
        }

        return $config;
    }
}