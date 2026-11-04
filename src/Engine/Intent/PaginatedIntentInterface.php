<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Intent;

use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;

interface PaginatedIntentInterface
{
    public function getPaginatorConfig(): PaginatorConfig;

    public function getPaginatorQueryParameter(): ?string;

    public function setPaginatorQueryParameter(?string $queryParameter): void;
}
