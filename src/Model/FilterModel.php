<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Util\PtableInferrableInterface;

/**
 * Class FilterModel
 */
class FilterModel extends Model implements PtableInferrableInterface
{
    use DocumentsFilterModelTrait, PtableInferrableTrait;

    protected static $strTable = FilterContainer::TABLE_NAME;

    private string $_formName;

    public function getFormName(): string
    {
        return $this->_formName ??= static::generateFormName($this);
    }

    /**
     * @template TTraverse as \Traversable<int, FilterModel>
     * @return Collection<FilterModel>&TTraverse
     */
    public static function findByPid(int $pid, ?bool $published = null): Collection
    {
        $result = $published !== null
            ? static::findBy(['pid=?', 'published=?'], [$pid, $published], ['order' => 'sorting'])
            : static::findBy(['pid=?'], [$pid], ['order' => 'sorting']);

        if (!$result) {
            return new Collection([], static::getTable());
        }

        if (!$result instanceof Collection) {
            return new Collection([$result], static::getTable());
        }

        return $result;
    }

    public static function generateFormName(FilterModel|array $model_or_row): string
    {
        if (\is_array($model_or_row))
        {
            $formAlias = $model_or_row['formAlias'] ?? null;
            $formId = $model_or_row['id'] ?? null;
        }
        else
        {
            $formAlias = $model_or_row->formAlias;
            $formId = $model_or_row->id;
        }

        return \preg_replace(
            '/[^A-Za-z0-9\-._]/',
            '',
            \trim((string) $formAlias) ?: (string) $formId
        );
    }
}