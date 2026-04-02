<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags;

use Codefog\TagsBundle\Manager\ManagerInterface;

readonly class CfgTagsJoinAttribute
{
    public const NAME = 'codefog_tags';

    public function __construct(
        public string           $joinTable,
        public string           $joinAlias,
        public string           $tagsField,
        public string           $dcTable,
        public ManagerInterface $manager,
    ) {}
}