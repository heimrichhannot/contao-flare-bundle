<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrableInterface;

/**
 * Class FilterModel
 */
#[\AllowDynamicProperties]
class FilterModel extends Model implements PtableInferrableInterface
{
    use DocumentsFilterModelTrait, PtableInferrableTrait;

    protected static $strTable = FilterContainer::TABLE_NAME;

    private string $_alias;

    public function getFilterIdentifier(): string
    {
        return \sprintf('%s.id=%s', static::$strTable, $this->id);
    }

    public function getFilterElementType(): string
    {
        return (string) $this->type;
    }

    public function getFilterTargetAlias(): string
    {
        return (string) $this->targetAlias;
    }

    /**
     * The filter's name in the URL query and the form: its alias, or its id as fallback.
     */
    public function getFilterAlias(): string
    {
        return $this->_alias ??= static::generateFormName($this);
    }

    public function getFilterData(): array
    {
        return $this->arrData;
    }

    public function getFilterProperty(string $name): mixed
    {
        return $this->{$name};
    }

    /**
     * @template TTraverse as \Traversable<int, FilterModel>
     * @return Collection<FilterModel>&TTraverse
     */
    public static function findByPid(int $pid, ?bool $published = null): Collection
    {
        $result = $published !== null
            ? static::findBy(['pid=?', 'published=?', 'tstamp>0'], [$pid, $published], ['order' => 'sorting'])
            : static::findBy(['pid=?'], [$pid], ['order' => 'sorting']);

        if (!$result) {
            return new Collection([], static::getTable());
        }

        if (!$result instanceof Collection) {
            return new Collection([$result], static::getTable());
        }

        return $result;
    }

    /**
     * Generates a form name based on the provided filter model or array representation of a row.
     *
     * @param FilterModel|array $model_or_row The filter model instance or an array representing the row.
     * @return string The generated form name.
     * @throws \RuntimeException If unable to generate a form name due to missing or invalid data.
     */
    public static function generateFormName(FilterModel|array $model_or_row): string
    {
        if (\is_array($model_or_row))
        {
            $alias = $model_or_row['alias'] ?? null;
            $filterId = $model_or_row['id'] ?? null;
        }
        else
        {
            $alias = $model_or_row->alias;
            $filterId = $model_or_row->id;
        }

        $alias = \preg_replace('/[^A-Za-z0-9\-._]/', '', \trim((string) $alias));

        $name = (string) ($alias ?: $filterId);

        if ($name !== '') {
            return $name;
        }

        throw new \RuntimeException('Unable to generate form name for filter model.');
    }

    /**
     * The form names used by more than one of the given rows, compared as {@see generateFormName()} normalizes them,
     * since that is the alias the filters get at runtime.
     *
     * @param iterable<array{id?: int|string, alias?: string|null}> $rows
     *
     * @return list<string> Each duplicated form name once, in order of first occurrence.
     */
    public static function findDuplicateFormNames(iterable $rows): array
    {
        $counts = [];

        foreach ($rows as $row)
        {
            $name = self::generateFormName($row);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        return \array_map('strval', \array_keys(\array_filter($counts, static fn (int $count): bool => $count > 1)));
    }

    /**
     * The parent-table inference columns are element-owned and therefore `elm_`-prefixed (§4.1).
     */
    protected static function getPtableInferColumn(string $name): string
    {
        return 'elm_' . $name;
    }

    public function __get($strKey)
    {
        return match ($strKey) {
            'published' => (bool) parent::__get($strKey),
            default => parent::__get($strKey),
        };
    }
}
