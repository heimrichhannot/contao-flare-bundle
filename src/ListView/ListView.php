<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use Contao\Model;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\View\InteractiveView;
use HeimrichHannot\FlareBundle\ListView\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\FormInterface;

/**
 * Represents a list view, providing access to entries, form components,
 * pagination, and sorting mechanisms.
 *
 * Meant to be used in Twig templates.
 * @todo(@ericges): Remove in 0.1.0
 * @deprecated Use {@see InteractiveView} instead.
 */
class ListView
{
    private array $readerUrls = [];

    /**
     * @internal Use {@see ListViewBuilder} (inject {@see ListViewBuilderFactory}) to create a new instance.
     */
    public function __construct(
        private readonly InteractiveContext $interactiveConfig,
        private readonly ListSpecification  $listSpecification,
        private readonly InteractiveView    $interactiveProjection,
    ) {}

    /**
     * Returns the list context for this list view.
     *
     * @api Use in twig templates to access the list context of a list.
     */
    public function getInteractiveConfig(): InteractiveContext
    {
        return $this->interactiveConfig;
    }

    /**
     * Returns the list definition for this list view.
     *
     * @api Use in twig templates to access the list definition of a list.
     */
    public function getListSpecification(): ListSpecification
    {
        return $this->listSpecification;
    }

    /**
     * Returns the interactive list projection for this list view.
     *
     * @api Use in twig templates to access the interactive projection of a list.
     */
    public function getInteractiveProjection(): InteractiveView
    {
        return $this->interactiveProjection;
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
     * Returns the models for the entries of this list view.
     *
     * @api Use in twig templates to access the models of a list.
     */
    public function getModels(): iterable
    {
        return $this->interactiveProjection->getModels();
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
        return $this->interactiveProjection->getModel((int) $id);
    }

    /**
     * Returns the URL to the details page of the given model.
     *
     * @param Model|int|string $id
     * @return string|null
     */
    public function to(Model|int|string ...$args): ?string
    {
        return $this->interactiveProjection->to(...$args);
    }
}