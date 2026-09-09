<?php

namespace HeimrichHannot\FlareBundle\Filter;

final readonly class Formula
{
    public function __construct(
        /** @var Proposition[] $propositions */
        public array $propositions = [],
    ) {
        foreach ($this->propositions as $proposition) {
            if (!$proposition instanceof Proposition) {
                throw new \InvalidArgumentException('Propositions must be instances of Proposition');
            }
        }
    }
}
