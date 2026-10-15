<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\HeimrichHannotUtils\FilterElement;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\UtilsBundle\Dca\SearchVectorField;
use HeimrichHannot\UtilsBundle\SearchVector\SearchVectorQuery;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Searches the search vector field of the list's data container, see heimrichhannot/contao-utils-bundle.
 */
#[AsFilterElement(
    type: self::TYPE,
    formType: TextType::class,
)]
class SearchVectorElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public const TYPE = 'flare_search_vector';

    public function __construct(
        private readonly SearchVectorQuery   $searchVectorQuery,
        private readonly ContaoFramework     $contaoFramework,
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $value = $inv->getValue();
        if (!\is_string($value) || '' === $value) {
            return;
        }

        $condition = $this->searchVectorQuery->createCondition(
            table: $inv->list->dc,
            search: $value,
            alias: $qb->alias(),
            parameterPrefix: 'search_vector',
        );

        if (!$condition) {
            return;
        }

        [$sql, $parameters] = $condition;

        $qb->where($sql, $parameters);
    }

    public function handleFormTypeOptions(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['label'] = 'label.text';
        $event->options['required'] = false;

        if ($label = $event->filter->label) {
            $event->options['label'] = $label;
        }

        if ($placeholder = $event->filter->placeholder) {
            $event->options['attr']['placeholder'] = $placeholder;
        }
    }

    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): ?string
    {
        $prefill = (string) $filter->prefill;

        return '' !== $prefill ? $prefill : null;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $this->addSearchVectorMessage($config->getListModel()->dc);

        $relevance = self::isRelevanceSupported() ? ',sortByRelevance' : '';

        if ($config->getFilterModel()?->intrinsic) {
            return '{filter_legend},prefill' . $relevance;
        }

        return ($relevance ? '{filter_legend}' . $relevance . ';' : '') . '{form_legend},label,placeholder';
    }

    /**
     * Sorting by relevance needs SearchVectorQuery::createRelevance(), which older versions of
     * heimrichhannot/contao-utils-bundle do not have.
     */
    public static function isRelevanceSupported(): bool
    {
        return \method_exists(SearchVectorQuery::class, 'createRelevance');
    }

    private function addSearchVectorMessage(?string $table): void
    {
        if (!$table) {
            return;
        }

        $this->contaoFramework->getAdapter(Controller::class)->loadDataContainer($table);

        if (!$configuration = SearchVectorField::getRegistrations()[$table] ?? null) {
            Message::addError($this->translator->trans('search_vector.missing_field', [
                '%table%' => $table,
            ], 'flare'));

            return;
        }

        Message::addInfo($this->translator->trans('search_vector.info', [
            '%table%' => $table,
            '%field%' => $configuration->getFieldName(),
            '%fields%' => \implode(', ', $configuration->getFields()),
        ], 'flare'));
    }
}
