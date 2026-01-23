<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use Contao\Model;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\FilterDefinition;
use HeimrichHannot\FlareBundle\Filter\FilterDefinitionCollection;
use HeimrichHannot\FlareBundle\List\ListDataSource;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

/**
 * Represents a list view, providing access to entries, form components,
 * pagination, and sorting mechanisms.
 *
 * Meant to be used in Twig templates.
 */
class ListView
{
    private iterable $entries;
    private FormInterface $formComponent;
    private Paginator $paginator;
    private array $models = [];
    private array $readerUrls = [];

    /**
     * @internal Use {@see ListViewBuilder} (inject {@see ListViewBuilderFactory}) to create a new instance.
     */
    public function __construct(
        private readonly ContentContext            $contentContext,
        private readonly ListDefinition            $listDefinition,
        private readonly ListViewResolverInterface $resolver,
        private ?PaginatorConfig                   $paginatorConfig = null,
        private ?SortDescriptor                    $sortDescriptor = null,
    ) {}

    /**
     * Returns the content context for this list view.
     *
     * @api Use in twig templates to access the content context of a list.
     */
    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    /**
     * Returns the list definition for this list view.
     *
     * @api Use in twig templates to access the list definition of a list.
     */
    public function getListDefinition(): ListDefinition
    {
        return $this->listDefinition;
    }

    /**
     * Returns the source list model for this list view.
     *
     * @api Use in twig templates to access the source list model of a list, if provided.
     */
    public function getListModel(): ?ListDataSource
    {
        // todo: refactor
        return $this->getListDefinition()->getDataSource();
    }

    public function getFilters(): FilterDefinitionCollection
    {
        return $this->getListDefinition()->getFilters();
    }

    public function addFilterDefinition(FilterDefinition $filterDefinition): static
    {
        // todo(@ericges): Add more methods to ease filter definition management on this class
        $this->getListDefinition()->getFilters()->add($filterDefinition);
        return $this;
    }

    /**
     * Returns the entries for this list view.
     *
     * @api Use in twig templates to access the entries of a list.
     */
    public function getEntries(): iterable
    {
        if (!isset($this->entries)) {
            $this->entries = $this->resolver->getEntries($this);
        }

        return $this->entries;
    }

    /**
     * Returns the form component for this list view.
     *
     * @api Use in twig templates to access the form of a list.
     */
    public function getFormComponent(): FormInterface
    {
        if (!isset($this->formComponent)) {
            $this->formComponent = $this->resolver->getForm($this);
        }

        return $this->formComponent;
    }

    /**
     * Returns the paginator for this list view.
     *
     * @api Use in twig templates to access the paginator of a list.
     */
    public function getPaginator(): Paginator
    {
        if (!isset($this->paginator)) {
            $this->paginator = $this->resolver->getPaginator($this);
        }

        return $this->paginator;
    }

    /**
     * Returns the paginator configuration for this list view.
     *
     * @api Use in twig templates to access the paginator configuration of a list.
     */
    public function getPaginatorConfig(): PaginatorConfig
    {
        if (!isset($this->paginatorConfig)) {
            $this->paginatorConfig = $this->resolver->getPaginatorConfig($this);
        }

        return $this->paginatorConfig;
    }

    /**
     * Returns the sort descriptor for this list view.
     *
     * @api Use in twig templates to access the sort descriptor of a list.
     */
    public function getSortDescriptor(): ?SortDescriptor
    {
        if (!isset($this->sortDescriptor)) {
            $this->sortDescriptor = $this->resolver->getSortDescriptor($this);
        }

        return $this->sortDescriptor;
    }

    /**
     * Returns the model for the given ID.
     *
     * @api Use in twig templates to access the model of a list entry.
     */
    public function getModel(int|string $id): Model
    {
        $id = (int) $id;

        if (!isset($this->models[$id])) {
            $this->models[$id] = $this->resolver->getModel($this, $id);
        }

        return $this->models[$id];
    }

    /**
     * Returns the URL to the details page of the given model.
     *
     * @param Model|int|string $id
     * @return string|null
     * #mago-expect lint:halstead This method is not complex.
     */
    public function getDetailsPageUrl(Model|int|string $id): ?string
    {
        if ($id instanceof Model) {
            $id = $id->id;
        }

        $id = (int) $id;

        if (!isset($this->readerUrls[$id])) {
            $this->readerUrls[$id] = $this->resolver->getDetailsPageUrl($this, $id);
        }

        return $this->readerUrls[$id];
    }

    /**
     * Alias for {@see self::getDetailsPageUrl}.
     *
     * @param Model|int|string ...$args
     * @return string|null
     * @see self::getDetailsPageUrl
     */
    public function to(Model|int|string ...$args): ?string
    {
        return $this->getDetailsPageUrl(...$args);
    }
}