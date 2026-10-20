<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\HeimrichHannotUtils\FilterElement;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListType\EventsListType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use HeimrichHannot\UtilsBundle\Dca\SearchVectorFieldConfiguration;
use HeimrichHannot\UtilsBundle\SearchVector\SearchVectorConfigurationResolver;
use HeimrichHannot\UtilsBundle\SearchVector\SearchVectorQuery;
use Psr\Log\LoggerInterface;
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
    public const TYPE = 'huh_utils_searchVector';

    /**
     * Each word of the search adds placeholders to the condition and the relevance, so longer searches are cut.
     */
    public const MAX_SEARCH_LENGTH = 200;

    public function __construct(
        private readonly SearchVectorQuery                 $searchVectorQuery,
        private readonly SearchVectorConfigurationResolver $configurationResolver,
        private readonly FilterContainer                   $filterContainer,
        private readonly TranslatorInterface               $translator,
        private readonly LoggerInterface                   $logger,
    ) {}

    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $value = self::normalizeSearch($inv->getValue());
        if (null === $value) {
            return;
        }

        try
        {
            $condition = $this->searchVectorQuery->createCondition(
                table: $inv->list->dc,
                search: $value,
                alias: $qb->alias(),
                parameterPrefix: 'search_vector',
            );
        }
        catch (\InvalidArgumentException $e)
        {
            // The table has no search vector field, or its stemmer or normalizer is not registered
            $this->logger->error(\sprintf(
                '[FLARE] Search vector filter cannot search list %s (ID %s), filter ID %s: %s',
                $inv->list->dc, (string) ($inv->list->getDataSource()?->getListProperty('id') ?? 'N/A'),
                (string) ($inv->filter->getDataSource()?->getFilterProperty('id') ?? 'N/A'), $e->getMessage(),
            ), ['exception' => $e]);

            $qb->abort();
        }

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
        $event->options['attr']['maxlength'] = self::MAX_SEARCH_LENGTH;

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

        // Event lists are sorted by date, see GroupsEntriesTrait::groupEntriesByDate()
        $sortable = EventsListType::TYPE !== $config->getListModel()->type;

        if ($config->getFilterModel()?->intrinsic) {
            return '{filter_legend},prefill' . ($sortable ? ',sortByRelevance' : '');
        }

        return ($sortable ? '{filter_legend},sortByRelevance;' : '') . '{form_legend},label,placeholder';
    }

    /**
     * Returns the trimmed search, cut at a word boundary to MAX_SEARCH_LENGTH characters, or null if there is none.
     */
    public static function normalizeSearch(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = \trim($value);

        if (\mb_strlen($value) > self::MAX_SEARCH_LENGTH)
        {
            $value = \mb_substr($value, 0, self::MAX_SEARCH_LENGTH);
            $lastSpace = \mb_strrpos($value, ' ');

            if (false !== $lastSpace) {
                $value = \rtrim(\mb_substr($value, 0, $lastSpace));
            }
        }

        return '' !== $value ? $value : null;
    }

    /**
     * Refuses to choose the filter type if the table of the list cannot be searched. A filter that already has the
     * type can still be saved, so it can be unpublished after its table broke; its palette shows the error.
     */
    #[AsCallback(FilterContainer::TABLE_NAME, 'fields.type.save')]
    public function onSaveType(mixed $value, DataContainer $dc): mixed
    {
        // The save callback runs before the record is written, so the row still holds the previous type
        if (self::TYPE !== $value || self::TYPE === (DcaHelper::rowOf($dc)['type'] ?? null)) {
            return $value;
        }

        [, $listModel] = $this->filterContainer->getModelsFromDataContainer($dc, ignoreType: true);

        if ($table = $listModel?->dc) {
            // Contao shows the message of the exception at the field and does not save it
            $this->resolveConfiguration($table);
        }

        return $value;
    }

    private function addSearchVectorMessage(?string $table): void
    {
        if (!$table) {
            return;
        }

        try
        {
            $configuration = $this->resolveConfiguration($table);
        }
        catch (\InvalidArgumentException $e)
        {
            Message::addError($e->getMessage());

            return;
        }

        Message::addInfo($this->translator->trans('search_vector.info', [
            '%table%' => $table,
            '%field%' => $configuration->getFieldName(),
            '%fields%' => \implode(', ', $configuration->getFields()),
        ], 'flare'));
    }

    /**
     * @throws \InvalidArgumentException if the table has no search vector field, or its stemmer or normalizer is not
     *                                   registered
     */
    private function resolveConfiguration(string $table): SearchVectorFieldConfiguration
    {
        try
        {
            $configuration = $this->configurationResolver->getConfiguration($table);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new \InvalidArgumentException($this->translator->trans('search_vector.missing_field', [
                '%table%' => $table,
            ], 'flare'), previous: $e);
        }

        try
        {
            $this->configurationResolver->getStemmer($configuration);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new \InvalidArgumentException($this->translator->trans('search_vector.missing_stemmer', [
                '%table%' => $table,
                '%service%' => $configuration->getStemmer(),
            ], 'flare'), previous: $e);
        }

        try
        {
            $this->configurationResolver->getNormalizer($configuration);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new \InvalidArgumentException($this->translator->trans('search_vector.missing_normalizer', [
                '%table%' => $table,
                '%service%' => $configuration->getNormalizer(),
            ], 'flare'), previous: $e);
        }

        return $configuration;
    }
}
