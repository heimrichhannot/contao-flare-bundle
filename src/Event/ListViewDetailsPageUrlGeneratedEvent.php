<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Model;
use Contao\PageModel;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\EventDispatcher\Event;

class ListViewDetailsPageUrlGeneratedEvent extends Event
{
    public function __construct(
        private readonly ListModel      $listModel,
        private readonly ContentContext $contentContext,
        private readonly Model          $model,
        private string                  $autoItem,
        private PageModel               $page,
        private string                  $url,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getContentContext(): ContentContext
    {
        return $this->contentContext;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    public function getAutoItem(): string
    {
        return $this->autoItem;
    }

    public function setAutoItem(string $autoItem): void
    {
        $this->autoItem = $autoItem;
    }

    public function getPage(): PageModel
    {
        return $this->page;
    }

    public function setPage(PageModel $page): void
    {
        $this->page = $page;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}