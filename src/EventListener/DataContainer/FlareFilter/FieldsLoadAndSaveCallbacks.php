<?php

namespace HeimrichHannot\FlareBundle\EventListener\DataContainer\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Util\DateTimeHelper;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal For internal use only. Do not call this class or its methods directly.
 */
readonly class FieldsLoadAndSaveCallbacks
{
    public const TABLE_NAME = FilterContainer::TABLE_NAME;

    public function __construct(
        private FilterElementRegistry $filterElementRegistry,
        private RequestStack          $requestStack,
    ) {}

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPublished.save')]
    public function onLoadField_fieldPublished(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'published', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStart.save')]
    public function onLoadField_fieldStart(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'start', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldStop.save')]
    public function onLoadField_fieldStop(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'stop', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPid.save')]
    public function onLoadField_fieldPid(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'pid', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.fieldPtable.save')]
    public function onLoadField_fieldPtable(mixed $value, DataContainer $dc): string
    {
        return $value ?: DcaHelper::tryGetColumnName($dc, 'ptable', '');
    }

    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.load')]
    public function onLoadField_intrinsic(mixed $value, DataContainer $dc): bool
    {
        $value = (bool) $value;

        $request = $this->requestStack->getCurrentRequest();
        if ($request?->getMethod() === 'POST' && $request?->request->get('FORM_SUBMIT') === self::TABLE_NAME)
        {
            // do not disable intrinsic field if form is being submitted
            // otherwise the save callback will not be called
            return $value;
        }

        if (!$row = DcaHelper::rowOf($dc)) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired())
        {
            $eval = &$GLOBALS['TL_DCA'][self::TABLE_NAME]['fields']['intrinsic']['eval'];

            $eval['disabled'] = true;

            return true;
        }

        return $value;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.intrinsic.save')]
    public function onSaveField_intrinsic(mixed $value, DataContainer $dc): mixed
    {
        if ($value || !$row = DcaHelper::rowOf($dc)) {
            return $value;
        }

        if ($this->filterElementRegistry->get($row['type'] ?? null)?->isIntrinsicRequired()) {
            return '1';
        }

        return $value;
    }

    #[AsCallback(self::TABLE_NAME, 'fields.configureStart.save')]
    #[AsCallback(self::TABLE_NAME, 'fields.configureStop.save')]
    public function onSaveField_configureDate(string $value, DataContainer $dc): string
    {
        if (!$value) {
            return $value;
        }

        $modifyField = match ($dc->field) {
            'configureStop' => 'stopAt',
            'configureStart' => 'startAt',
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

    #[AsCallback(self::TABLE_NAME, 'fields.startAt.load')]
    #[AsCallback(self::TABLE_NAME, 'fields.stopAt.load')]
    public function onLoadField_startStopAt(string $value, DataContainer $dc): string
    {
        if (!isset($GLOBALS['TL_DCA'][self::TABLE_NAME]['fields'][$dc->field])) {
            return $value;
        }

        $configuredBy = match ($dc->field) {
            'startAt' => 'configureStart',
            'stopAt' => 'configureStop',
            default => null,
        };

        if (!$configuredBy || !$model = DcaHelper::modelOf($dc)) {
            return $value;
        }

        $mode = $model->{$configuredBy} ?? 'date';

        if ($mode === 'date') {
            return \is_numeric($value) ? $value : (\strtotime($value) ?: '');
        }

        $eval = &$GLOBALS['TL_DCA'][self::TABLE_NAME]['fields'][$dc->field]['eval'];
        unset($eval['rgxp']);
        unset($eval['datepicker']);
        $eval['tl_class'] = \str_replace('wizard', '', $eval['tl_class'] ?? '');

        return $value;
    }
}