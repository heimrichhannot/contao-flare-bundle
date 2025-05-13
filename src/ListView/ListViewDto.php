<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use Contao\Model;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormInterface;

class ListViewDto
{
    private iterable $entries;
    private FormInterface $formComponent;
    private Paginator $paginator;
    private array $models = [];
    private array $readerUrls = [];

    public function __construct(
        private readonly ListModel                 $listModel,
        private readonly ListViewResolverInterface $resolver,
        private ?PaginatorConfig                   $paginatorConfig = null,
        private ?SortDescriptor                    $sortDescriptor = null,
        private ?string                            $formName = null,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFormName(): string
    {
        if (!isset($this->formName)) {
            $this->formName = $this->resolver->getFormName($this);
        }

        return $this->formName;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(): iterable
    {
        if (!isset($this->entries)) {
            $this->entries = $this->resolver->getEntries($this);
        }

        return $this->entries;
    }

    /**
     * @throws FlareException
     */
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
        $id = \intval($id);

        if (!isset($this->models[$id])) {
            $this->models[$id] = $this->resolver->getModel($this, $id);
        }

        return $this->models[$id];
    }

    public function getDetailsPageUrl(int|string $id): ?string
    {
        $id = \intval($id);

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