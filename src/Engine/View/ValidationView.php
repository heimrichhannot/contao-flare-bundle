<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\View;

use Contao\Model;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Reader\BackLink;

class ValidationView implements ViewInterface
{
    use HandlesModelsTrait;

    /** @var array<string, ?array<string, mixed>> the loaded rows, misses as null */
    private array $entriesByAutoItem = [];

    public function __construct(
        private readonly ValidationLoaderInterface $loader,
        private readonly string                    $table,
        private readonly string                    $autoItemField,
        private readonly ?BackLink                 $backLink = null,
    ) {}

    public function getBackLink(): ?BackLink
    {
        return $this->backLink;
    }

    /**
     * @return array<string, mixed>|null the member row, or null on a miss
     *
     * @throws FlareException
     */
    public function getEntryByAutoItem(string $autoItem): ?array
    {
        if (!\array_key_exists($autoItem, $this->entriesByAutoItem)) {
            $this->entriesByAutoItem[$autoItem] = $this->loader->fetchEntryByAutoItem($autoItem);
        }

        return $this->entriesByAutoItem[$autoItem];
    }

    /**
     * @throws FlareException
     */
    public function getModelByAutoItem(string $autoItem): ?Model
    {
        return $this->fetchModel($this->table, $autoItem, $this->getEntryByAutoItem(...), $this->autoItemField);
    }
}
