<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\Registry;

use Codefog\TagsBundle\Manager\ManagerInterface;
use Psr\Container\ContainerInterface;

class CfgTagsManagersResolver
{

    public function __construct(
        private CfgTagsManagersRegistry $registry,
        private ContainerInterface $managerLocator,
    ) {}

    public function get(string $table, string $field): ?ManagerInterface
    {
        if (!$serviceId = $this->registry->findServiceId($table, $field)) {
            return null;
        }

        $service = $this->managerLocator->get($serviceId);

        if (!$service instanceof ManagerInterface) {
            return null;
        }

        return $service;
    }
}