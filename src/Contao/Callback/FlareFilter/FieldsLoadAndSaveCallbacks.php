<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsLoadAndSaveCallbacks
{
    public const TABLE_NAME = FilterContainer::TABLE_NAME;

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPublished.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPublished.save')]
    public function onLoadField_fieldPublished(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'published', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStart.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStart.save')]
    public function onLoadField_fieldStart(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'start', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStop.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldStop.save')]
    public function onLoadField_fieldStop(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'stop', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPid.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPid.save')]
    public function onLoadField_fieldPid(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'pid', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPtable.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_fieldPtable.save')]
    public function onLoadField_fieldPtable(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'ptable', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.elm_configureStart.save')]
    #[AsCallback(self::TABLE_NAME, 'fields.elm_configureStop.save')]
    public function onSaveField_configureDate(string $value, DataContainer $dc): string
    {
        if (!$value) {
            return $value;
        }

        $modifyField = match ($dc->field) {
            'elm_configureStop' => 'elm_stopAt',
            'elm_configureStart' => 'elm_startAt',
            default => null,
        };

        if ($modifyField)
        {
            $this->onConfigureDateSave($modifyField, $value, $dc);
        }

        return $value;
    }

    private function onConfigureDateSave(string $field, string $value, DataContainer $dc): void
    {
        if (!$model = DcaHelper::modelOf($dc)) {
            return;
        }

        if (!$timeString = DateTimeHelper::spanToTimeString($value)) {
            return;
        }

        $model->{$field} = $timeString;
        $model->save();
    }
}
