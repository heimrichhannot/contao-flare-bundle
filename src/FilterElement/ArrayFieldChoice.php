<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\Contract\PaletteContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

/*#[AsFilterElement(
    type: ArrayFieldChoice::TYPE,
    palette: '{filter_legend},fieldGeneric,isMultiple,preselect',
    formType: ChoiceType::class,
)]*/ // todo: continue work
class ArrayFieldChoice implements FormTypeOptionsContract, PaletteContract
{
    public const TYPE = 'flare_arrayFieldModelChoice';

    public function __construct(
        private readonly ChoicesBuilderFactory $choicesBuilderFactory,
    ) {}

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        // TODO: Implement __invoke() method.
    }

    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['multiple'] = $event->filterDefinition->isMultiple;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        return null;
    }

    #[AsFilterCallback(self::TYPE, 'fields.preselect.load')]
    public function onLoad_preselect(
        mixed          $value,
        ?DataContainer $dc,
        FilterModel    $filterModel,
        ListModel $listModel
    ): mixed {
        if (!($table = $listModel->dc) || !($valueField = $filterModel->fieldGeneric)) {
            return $value;
        }

        $dca = &$GLOBALS['TL_DCA'][$dc->table]['fields'][$dc->field];

        $choices = $this->choicesBuilderFactory
            ->createChoicesBuilder()
            ->setModelSuffix('[%id%]')
            ->enable();

        $dca['inputType'] = 'select';
        $dca['eval']['multiple'] = $filterModel->isMultiple;
        $dca['eval']['chosen'] = true;
        $dca['eval']['includeBlankOption'] = true;
        $dca['options_callback'] = static fn(DataContainer $dc): array => $choices->buildOptions();

        foreach ($this->getDistinctValues($table, $valueField) as $option) {
            $choices->add($option, $option);
        }

        return $value;
    }
}