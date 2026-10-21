<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Value\Choice;

/**
 * A choice that names its own key (SPEC §7.2).
 */
interface ChoiceObjectInterface
{
    /** Namespace of the key, e.g. the table "tl_news_archive"; null if the key has none. */
    public function getChoiceScope(): ?string;

    /** Key within the namespace, e.g. "5". */
    public function getChoiceLocalKey(): string;

    /**
     * Placeholders for the label translation.
     *
     * @return array<string, scalar>
     */
    public function getLabelParameters(): array;
}
