<?php

namespace HeimrichHannot\FlareBundle\Sort\Codec;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Sort\SortOrder;
use HeimrichHannot\FlareBundle\Sort\SortOrderSequence;

readonly class SortOrderSequenceSQLCodec
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function toSql(SortOrderSequence $sequence, bool $ignoreCase = false): string
    {
        if ($sequence->isEmpty()) {
            return '';
        }

        $parts = \array_map(
            function (SortOrder $o) use ($ignoreCase): string {
                $col = $this->connection->quoteIdentifier($o->getQualifiedColumn());

                if ($ignoreCase) {
                    $col = "LOWER({$col})";
                }

                return "{$col} {$o->getDirection()}";
            },
            $sequence->getItems()
        );

        return \implode(', ', $parts);
    }
}