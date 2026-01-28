<?php

namespace HeimrichHannot\FlareBundle\Context\Factory;

use HeimrichHannot\FlareBundle\Context\ValidationConfig;
use HeimrichHannot\FlareBundle\View\InteractiveView;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ValidationConfigFactory
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    public function createFromInteractiveView(InteractiveView $interactiveView): ValidationConfig
    {
        $config = new ValidationConfig(
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