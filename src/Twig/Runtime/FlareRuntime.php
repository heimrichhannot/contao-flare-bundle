<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Twig\Runtime;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendTemplate;
use Contao\Model;
use Contao\Model\Collection;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryRelationResolver;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\Exception\UndefinedRelationError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Util\CallableWrapper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class FlareRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ContaoFramework          $framework,
        private EventDispatcherInterface $eventDispatcher,
        private ProjectorRegistry        $projectorRegistry,
        private EntryRelationResolver    $relationResolver,
    ) {}

    public function project(ListCompound $list, IntentInterface $intent): ViewInterface
    {
        return $this->projectorRegistry->getProjectorFor($list, $intent)->project($list, $intent);
    }

    /**
     * The content elements of the entry's record as a lazy callable.
     *
     * @throws MissingModelRefError for an entry without a modelRef
     */
    public function getTlContent(Entry $entry): ?callable
    {
        $ref = $entry->meta->modelRef ?? throw new MissingModelRefError();

        $framework = $this->framework;

        $text = self::once(static function () use ($framework, $ref): string {
            $elm = $framework->getAdapter(ContentModel::class)->findPublishedByPidAndTable($ref->id, $ref->table);

            if (!$elm) {
                return '';
            }

            $controller = $framework->getAdapter(Controller::class);
            $text = '';

            while ($elm->next())
            {
                $text .= $controller->getContentElement($elm->current());
            }

            return $text;
        });

        return new CallableWrapper($text);
    }

    /**
     * The Contao relation of an entry's column, like `Model::getRelated()`: a model for a to-one, a collection for
     * a to-many relation.
     *
     * @throws MissingModelRefError for an entry without a modelRef
     * @throws UndefinedRelationError for a column without relation
     */
    public function getRelated(Entry $entry, string $column): Model|Collection|null
    {
        return $this->relationResolver->resolve($entry, $column);
    }

    public function getEnclosure(Entry|array $entity, ?string $field = null): array
    {
        if ($entity instanceof Entry) {
            $entity = $entity->row;
        }

        $template = new FrontendTemplate();

        Controller::addEnclosuresToTemplate($template, $entity, $field ?? 'enclosure');

        return $template->getData()['enclosure'] ?? [];
    }

    /**
     * Without arguments, the reader's `entry` and the list of its `flare` engine are taken from the context; a
     * list loop's `entry` is no reader entry, so the context only counts when it holds `flare_reader`.
     */
    public function getSchemaOrg(array $context, ?Entry $entry = null, ?ListCompound $list = null): ?array
    {
        $inReader = isset($context['flare_reader']);

        if (!$entry && $inReader) {
            $entry = $context['entry'] ?? null;
        }

        if (!$entry instanceof Entry) {
            return null;
        }

        if (!$list && $inReader)
        {
            $engine = $context['flare'] ?? null;
            $list = $engine instanceof Engine ? $engine->getList() : null;
        }

        if (!$list) {
            return null;
        }

        $event = $this->eventDispatcher->dispatch(new ReaderSchemaOrgEvent($list, $entry));

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
