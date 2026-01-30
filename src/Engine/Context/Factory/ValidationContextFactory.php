<?php

namespace HeimrichHannot\FlareBundle\Engine\Context\Factory;

use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ValidationContextFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromInteractiveView(InteractiveView $interactiveView): ValidationContext
    {
        $config = new ValidationContext(
            entryCache: function () use ($interactiveView): ?array {
                return $interactiveView->issetEntries()
                    ? $interactiveView->getEntries()
                    : null;
            },
        );

        $violations = $this->validator->validate($interactiveView);

        if ($violations->count()) {
            throw new ValidationFailedException($config, $violations);
        }

        return $config;
    }
}