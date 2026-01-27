<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use Contao\Model;
use HeimrichHannot\FlareBundle\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\List\ListContext;
use HeimrichHannot\FlareBundle\List\ListDefinition;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolver;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Projector\Projection\InteractiveProjection;
use HeimrichHannot\FlareBundle\Projector\Projection\ValidationProjection;
use Symfony\Component\Form\FormInterface;

/**
 * Represents a list view, providing access to entries, form components,
 * pagination, and sorting mechanisms.
 *
 * Meant to be used in Twig templates.
 */
class ListView
{
    private array $models = [];
    private array $readerUrls = [];

    /**
     * @internal Use {@see ListViewBuilder} (inject {@see ListViewBuilderFactory}) to create a new instance.
     */
    public function __construct(
        private readonly ListContext               $listContext,
        private readonly ListDefinition            $listDefinition,
        private readonly ListViewResolver          $resolver,
        private readonly InteractiveProjection     $interactiveProjection,
        private readonly ValidationProjection      $validationProjection,
    ) {}

    /**
     * Returns the list context for this list view.
     *
     * @api Use in twig templates to access the list context of a list.
     */
    public function getListContext(): ListContext
    {
        return $this->listContext;
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
     * Returns the entries for this list view.
     *
     * @api Use in twig templates to access the entries of a list.
     */
    public function getEntries(): iterable
    {
        return $this->interactiveProjection->getEntries();
    }

    /**
     * Returns the form component for this list view.
     *
     * @api Use in twig templates to access the form of a list.
     */
    public function getFormComponent(): FormInterface
    {
        return $this->interactiveProjection->getForm();
    }

    /**
     * Returns the paginator for this list view.
     *
     * @api Use in twig templates to access the paginator of a list.
     */
    public function getPaginator(): Paginator
    {
        return $this->interactiveProjection->getPaginator();
    }

    /**
     * Returns the model for the given ID.
     *
     * @api Use in twig templates to access the model of a list entry.
     */
    public function getModel(int|string $id): Model
    {
        return $this->interactiveProjection->getModel((int) $id)
            ?? $this->validationProjection->getModel((int) $id);
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