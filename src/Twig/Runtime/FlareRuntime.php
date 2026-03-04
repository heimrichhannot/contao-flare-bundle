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
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
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

    public function makeFilter(string $filterAlias, array $options = []): FilterDefinition
    {
        return match ($filterAlias) {
            'eq' => (static function () use ($options): FilterDefinition {
                if (!$options) {
                    throw new \InvalidArgumentException('Missing required options for filter "eq"');
                }

                $prop = \array_key_first($options);
                $value = $options[$prop];

                return SimpleEquationElement::define(
                    equationLeft: $prop,
                    equationOperator: SqlEquationOperator::EQUALS,
                    equationRight: $value,
                );
            })(),
            default => throw new \InvalidArgumentException('Unknown filter alias: ' . $filterAlias)
        };
    }

    public function copyView(
        ListSpecification $spec,
        ContextInterface  $config,
        array             $filters = [],
    ): ViewInterface {
        $spec = clone $spec;
        $filterCollection = $spec->getFilters();

        foreach ($filters as $filter)
        {
            if (!$filter instanceof FilterDefinition && !\is_array($filter)) {
                throw new \InvalidArgumentException('Invalid filter definition');
            }

            if (\is_array($filter))
            {
                if (!isset($filter['alias'], $filter['options']) || !\is_string($filter['alias']) || !\is_array($filter['options'])) {
                    throw new \InvalidArgumentException('Invalid filter definition');
                }

                $filter = $this->makeFilter($filter['alias'], $filter['options']);
            }

            $filterCollection->add($filter);
        }

        return $this->project($spec, clone $config);
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

        return $this->getCallableWrapper($text);
    }

    public function getEnclosure(Model|array $entity, ?string $field = null): array
    {
        if ($entity instanceof Model) {
            $entity = $entity->row();
        }

        $template = new FrontendTemplate();
        $template->enclosure = [];

        Controller::addEnclosuresToTemplate($template, $entity, $field ?? 'enclosure');

        return $template->enclosure;
    }

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