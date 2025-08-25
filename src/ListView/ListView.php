<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use Contao\Model;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

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
        private readonly ListModel                 $listModel,
        private readonly ListViewResolverInterface $resolver,
        private ?PaginatorConfig                   $paginatorConfig = null,
        private ?SortDescriptor                    $sortDescriptor = null,
    ) {}

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getEntries(): iterable
    {
        if (!isset($this->entries)) {
            $this->entries = $this->resolver->getEntries($this);
        }

        return $this->entries;
    }

    public function getFormComponent(): FormInterface
    {
        if (!isset($this->formComponent)) {
            $this->formComponent = $this->resolver->getForm($this);
        }

        return $this->formComponent;
    }

    public function getPaginator(): Paginator
    {
        if (!isset($this->paginator)) {
            $this->paginator = $this->resolver->getPaginator($this);
        }

        return $this->paginator;
    }

    public function getPaginatorConfig(): PaginatorConfig
    {
        if (!isset($this->paginatorConfig)) {
            $this->paginatorConfig = $this->resolver->getPaginatorConfig($this);
        }

        return $this->paginatorConfig;
    }

    public function getSortDescriptor(): ?SortDescriptor
    {
        if (!isset($this->sortDescriptor)) {
            $this->sortDescriptor = $this->resolver->getSortDescriptor($this);
        }

        return $this->sortDescriptor;
    }

    public function getModel(int|string $id): Model
    {
        $id = (int) $id;

        if (!isset($this->models[$id])) {
            $this->models[$id] = $this->resolver->getModel($this, $id);
        }

        return $this->models[$id];
    }

    public function getDetailsPageUrl(int|string $id): ?string
    {
        $id = (int) $id;

        if (!isset($this->readerUrls[$id])) {
            $this->readerUrls[$id] = $this->resolver->getDetailsPageUrl($this, $id);
        }

        return $this->readerUrls[$id];
    }

    public function to(...$args): ?string
    {
        return $this->getDetailsPageUrl(...$args);
    }
}