<?php

namespace HeimrichHannot\FlareBundle\Util;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Dto\SqlQuery;

readonly class SqlHelper
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function findInSerializedArrayColumn(
        array|int|string $find,
        string           $column,
    ): string {
        $escaped = \array_map(static fn (string $value): string => \preg_quote((string) $value, '/'), (array) $find);
        $alternation = \implode('|', $escaped);
        $pattern = \sprintf('[{;]i:[0-9]+;(s:[0-9]+:"|i:)(%s)"?;(i:[0-9]+;|\})', $alternation);

        return \sprintf(
            'CONVERT(%s USING utf8mb4) REGEXP %s',
            $column,
            $this->connection->quote($pattern),
        );
    }
}