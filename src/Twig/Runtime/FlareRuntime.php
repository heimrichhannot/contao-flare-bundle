<?php

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use Contao\ContentModel;
use Contao\Controller;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\Template;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\ListView\ListView;
use HeimrichHannot\FlareBundle\Factory\ListViewBuilderFactory;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\SortDescriptor\SortDescriptor;
use Symfony\Component\Form\FormView;
use Twig\Extension\RuntimeExtensionInterface;

class FlareRuntime implements RuntimeExtensionInterface
{
    protected array $listViewCache = [];

    public function __construct(
        private readonly ListViewBuilderFactory $listViewBuilderFactory,
    ) {}

    /**
     * @throws FlareException
     */
    public function createFormView(ListView $container): FormView
    {
        return $container->getFormComponent()->createView();
    }

    /**
     * Returns a list view DTO for the given list model.
     *
     * @param array{
     *     form_name?: string,
     *     items_per_page?: int,
     *     sort?: array<string, string>,
     * } $options
     *
     * @throws FlareException
     */
    public function getFlare(ListModel|string|int $listModel, array $options = []): ListView
    {
        $cacheKey = $listModel->id . '@' . \md5(\serialize($options));

        if (isset($this->listViewCache[$cacheKey])) {
            return $this->listViewCache[$cacheKey];
        }

        $listModel = $this->getListModel($listModel);

        $paginatorConfig = new PaginatorConfig(
            itemsPerPage: $options['items_per_page'] ?? null,
        );

        $sortDescriptor = null;
        if (isset($options['sort'])) {
            $sortDescriptor = SortDescriptor::fromMap($options['sort']);
        }

        $contentContext = new ContentContext(
            context: ContentContext::CONTEXT_TWIG,
            contentModel: null,
            formName: $options['form_name'] ?? null,
        );

        $listView = $this->listViewBuilderFactory->create()
            ->setContentContext($contentContext)
            ->setListModel($listModel)
            ->setPaginatorConfig($paginatorConfig)
            ->setSortDescriptor($sortDescriptor)
            ->build();

        $this->listViewCache[$cacheKey] = $listView;

        return $listView;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getListModel(ListModel|string|int $listModel): ?ListModel
    {
        if ($listModel instanceof ListModel) {
            return $listModel;
        }

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $listModel = ListModel::findByPk((int) $listModel);

        if ($listModel instanceof ListModel) {
            return $listModel;
        }

        throw new \InvalidArgumentException('Invalid list model');
    }

    public function getTlContent(Model $model): ?callable
    {
        $table = $model->getTable();
        $id = $model->id;

        $text = Template::once(static function () use ($table, $id) {
            if (!$elm = ContentModel::findPublishedByPidAndTable($id, $table))
            {
                return '';
            }

            $text = '';

            while ($elm->next())
            {
                $text .= Controller::getContentElement($elm->current());
            }

            return $text;
        });

        return $this->getCallableWrapper($text);
    }

    public function getEnclosure(Model|array $entity, ?string $field = null)
    {
        if ($entity instanceof Model) {
            $entity = $entity->row();
        }

        $template = new FrontendTemplate();
        $template->enclosure = [];

        Controller::addEnclosuresToTemplate($template, $entity, $field ?? 'enclosure');

        return $template->enclosure;
    }

    public function getEnclosureFiles(Model|array|string|null $enclosure): array
    {
        if ($enclosure instanceof Model) {
            $enclosure = $enclosure->enclosure ?: null;
        }

        if (\is_string($enclosure)) {
            $enclosure = StringUtil::deserialize($enclosure, true);
        }

        if (!$enclosure) {
            return [];
        }

        $files = FilesModel::findMultipleByUuids(\array_values($enclosure));

        if ($files instanceof Collection) {
            return $files->fetchAll();
        }

        if (\is_array($files)) {
            return $files;
        }

        return [];
    }

    /**
     * @see \Contao\CoreBundle\Twig\Interop\ContextFactory::getCallableWrapper()
     * @return object{
     *     __invoke: callable(mixed ...$args): mixed,
     *     __toString: callable(): string,
     *     invoke: callable(mixed ...$args): mixed
     * }
     */
    private function getCallableWrapper(callable $callable): object
    {
        return new class($callable) implements \Stringable {
            /**
             * @var callable
             */
            private $callable;

            public function __construct(callable $callable)
            {
                $this->callable = $callable;
            }

            /**
             * Delegates call to callable, e.g. when in a Contao template context.
             */
            public function __invoke(mixed ...$args): mixed
            {
                return ($this->callable)(...$args);
            }

            /**
             * Called when evaluating "{{ var }}" in a Twig template.
             */
            public function __toString(): string
            {
                return (string) $this();
            }

            /**
             * Called when evaluating "{{ var.invoke() }}" in a Twig template. We do not cast
             * to string here, so that other types (like arrays) are supported as well.
             */
            public function invoke(mixed ...$args): mixed
            {
                return $this(...$args);
            }
        };
    }
}