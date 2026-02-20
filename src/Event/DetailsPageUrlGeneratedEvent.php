<?php

namespace HeimrichHannot\FlareBundle\Event;

use Contao\Model;
use Contao\PageModel;
use Symfony\Contracts\EventDispatcher\Event;

class DetailsPageUrlGeneratedEvent extends Event
{
    public function __construct(
        private readonly Model $model,
        private string         $autoItem,
        private PageModel      $page,
        private string         $url,
    ) {}

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