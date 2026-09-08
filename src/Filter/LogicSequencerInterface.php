<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Filter\Logic\LogicInterface;

interface LogicSequencerInterface
{
    /**
     * @param class-string<LogicInterface> $type
     * @param array<string, mixed> $options
     */
    public function add(string $type, array $options = [], ?string $targetAlias = null): static;

    /**
     * @return LogicStep[]
     */
    public function all(): array;

    public function abort(): never;
}
