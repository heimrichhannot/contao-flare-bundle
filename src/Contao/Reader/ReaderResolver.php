<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contao\Reader;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ContentContainer;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\Contao\ReaderResolution;
use HeimrichHannot\FlareBundle\Engine\Factory\EngineFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\ValidationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\View\ValidationView;
use HeimrichHannot\FlareBundle\Event\ReaderPageMetaEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Exception\ViewException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The only path from a reader element and the request's auto-item to its entry: list → intent → engine → view →
 * entry, once per request. The breadcrumb and the reader share the result, whoever asks first; failures are memoized
 * too. The cache lives here, not in request attributes (a fragment's sub-request never hands them back), and is
 * emptied between main requests.
 */
final class ReaderResolver implements ResetInterface
{
    /** @var array<string, ReaderResolution> keyed by "<tl_content.id>|<auto-item>" */
    private array $resolutions = [];

    private bool $autoItemRead = false;
    private ?string $autoItem = null;

    public function __construct(
        private readonly AutoItemReader             $autoItemReader,
        private readonly EngineFactory              $engineFactory,
        private readonly EventDispatcherInterface   $eventDispatcher,
        private readonly ListCompoundBuilderFactory $listFactory,
        private readonly ReaderElementFinder        $elementFinder,
        private readonly ValidationIntentFactory    $validationIntentFactory,
    ) {}

    /**
     * What $reader shows for the request's auto-item. Without an auto-item nothing is built or queried. A
     * {@see FlareException} while building is stored as the resolution's failure; other exceptions propagate and are
     * not memoized.
     */
    public function resolve(ContentModel $reader): ReaderResolution
    {
        if (($autoItem = $this->getAutoItem()) === null) {
            return new ReaderResolution($reader);
        }

        return $this->resolutions["{$reader->id}|{$autoItem}"] ??= $this->build($reader, $autoItem);
    }

    /**
     * The resolution of the current page's reader element, for the breadcrumb; null without an auto-item (nothing is
     * looked up then) or without exactly one visible reader on the page.
     */
    public function resolveForPage(): ?ReaderResolution
    {
        if ($this->getAutoItem() === null) {
            return null;
        }

        $reader = $this->elementFinder->find();

        return $reader !== null ? $this->resolve($reader) : null;
    }

    /**
     * Empties the memo and forgets the auto-item, for the next main request.
     */
    public function reset(): void
    {
        $this->resolutions = [];
        $this->autoItemRead = false;
        $this->autoItem = null;
    }

    /**
     * The auto-item, read once per request.
     */
    private function getAutoItem(): ?string
    {
        if (!$this->autoItemRead)
        {
            $this->autoItem = $this->autoItemReader->read();
            $this->autoItemRead = true;
        }

        return $this->autoItem;
    }

    private function build(ContentModel $reader, string $autoItem): ReaderResolution
    {
        $list = null;
        $intent = null;
        $engine = null;
        $view = null;

        try
        {
            $list = $this->listFactory->create($this->getListModel($reader))->build();
            $intent = $this->validationIntentFactory->createFromContent(contentModel: $reader, list: $list);
            $engine = $this->engineFactory->createEngine($intent, $list);
            $view = $engine->createView();

            if (!$view instanceof ValidationView) {
                throw ViewException::create(ValidationView::class, $view, method: __METHOD__);
            }

            $entry = $view->getEntryByAutoItem($autoItem);
        }
        catch (FlareException $e)
        {
            // A failed list arrives here as a FilterException: membership cannot be decided.
            return new ReaderResolution(
                contentModel: $reader,
                list: $list,
                intent: $intent,
                engine: $engine,
                view: $view instanceof ValidationView ? $view : null,
                failure: $e,
            );
        }

        if ($entry === null) {
            return new ReaderResolution($reader, $list, $intent, $engine, $view);
        }

        return new ReaderResolution(
            contentModel: $reader,
            list: $list,
            intent: $intent,
            engine: $engine,
            view: $view,
            entry: $entry,
            pageMeta: fn () => $this->eventDispatcher
                ->dispatch(new ReaderPageMetaEvent(contentModel: $reader, entry: $entry, list: $list))
                ->pageMeta,
        );
    }

    /**
     * @throws FlareException
     */
    private function getListModel(ContentModel $reader): ListModel
    {
        try
        {
            $listModel = $reader->getRelated(ContentContainer::FIELD_LIST);
        }
        catch (\Exception $e)
        {
            throw new FlareException('No list model found.', previous: $e, method: __METHOD__);
        }

        if (!$listModel instanceof ListModel) {
            throw new FlareException('No list model found.', method: __METHOD__);
        }

        return $listModel;
    }
}
