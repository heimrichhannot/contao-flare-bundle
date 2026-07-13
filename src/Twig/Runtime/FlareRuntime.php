<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use Contao\Controller;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Model;
use Contao\Model\Collection;
use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Type\FilterTypeInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use HeimrichHannot\FlareBundle\Util\CallableWrapper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class FlareRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProjectorRegistry        $projectorRegistry,
    ) {}

    public function project(ListSpecification $spec, ContextInterface $config): ViewInterface
    {
        return $this->projectorRegistry->getProjectorFor($spec, $config)->project($spec, $config);
    }

    /**
     * Creates a filter for programmatic use, e.g. `{% do flare.list.addFilter(flare_make_filter(...)) %}`.
     *
     * @param string $type A registered filter element type alias (config keys are the element's
     *   canonical config), or a filter type class-string (config keys are the type's options).
     * @param array<string, mixed> $config
     * @param array<string, mixed>|null $data Runtime data bag, as buildFilter() receives it.
     */
    public function makeFilter(string $type, array $config = [], ?array $data = null, ?string $alias = null): Filter
    {
        if (\is_a($type, FilterTypeInterface::class, true)) {
            return Filter::fromType($type, $config);
        }

        return new Filter(element: $type, config: $config, data: $data, alias: $alias);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getListModel(ListModel|string|int $listModel): ?ListModel
    {
        if ($listModel instanceof ListModel) {
            return $listModel;
        }

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

        $text = self::once(static function () use ($table, $id): string {
            if (!$elm = Model::getClassFromTable('tl_content')::findPublishedByPidAndTable($id, $table))
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

        return new CallableWrapper($text);
    }

    public function getEnclosure(Model|array $entity, ?string $field = null): array
    {
        if ($entity instanceof Model) {
            $entity = $entity->row();
        }

        $template = new FrontendTemplate();

        Controller::addEnclosuresToTemplate($template, $entity, $field ?? 'enclosure');

        return $template->getData()['enclosure'] ?? [];
    }

    /**
     * @deprecated Will be replaced in 0.2 with a solution using the virtual file system.
     */
    public function getEnclosureFiles(Model|array|string|null $enclosed): array
    {
        if ($enclosed instanceof Model) {
            $enclosed = $enclosed->enclosure ?: null;
        }

        if (\is_string($enclosed)) {
            $enclosed = StringUtil::deserialize($enclosed, true);
        }

        if (!$enclosed || !\is_array($enclosed)) {
            return [];
        }

        /** @var array $enclosure */
        $enclosure = $enclosed;

        $files = FilesModel::findMultipleByUuids(\array_values($enclosure));

        if ($files instanceof Collection) {
            return $files->fetchAll();
        }

        if (\is_array($files)) {
            return $files;
        }

        return [];
    }

    public function getSchemaOrg(array $context, ?Model $model = null, ?ListSpecification $list = null): ?array
    {
        $model ??= $context['model'] ?? null;
        if (!$model instanceof Model) {
            return null;
        }

        if (!$list)
        {
            $engine = $context['flare'] ?? null;
            if (!$engine instanceof Engine) {
                return null;
            }

            $list = $engine->getList();
        }

        $event = $this->eventDispatcher->dispatch(new ReaderSchemaOrgEvent($list, $model));

        return $event->data;
    }

    private static function once(callable $callback): callable
    {
        $result = null;

        return static function () use (&$callback, &$result): mixed {
            if ($callback !== null)
            {
                $result = $callback();
                $callback = null;
            }

            return $result;
        };
    }
}