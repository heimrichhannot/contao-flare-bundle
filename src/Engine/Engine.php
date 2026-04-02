<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;

final class Engine
{
    public function __construct(
        private readonly EngineModRegistry $engineModRegistry,
        private readonly ProjectorRegistry $projectorRegistry,
        private ContextInterface           $context,
        private ListSpecification          $list,
        private array                      $mods = [],
    ) {}

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getList(): ListSpecification
    {
        return $this->list;
    }

    /**
     * @throws FlareException
     */
    public function createView(): ViewInterface
    {
        $engine = \count($this->mods) ? clone $this : $this;

        foreach ($this->mods as $modConf)
        {
            ['type' => $type, 'config' => $config] = $modConf;

            $mod = $this->engineModRegistry->get($type)
                ?? throw new FlareException(\sprintf('No FLARE engine mod registered with type "%s".', $type));

            $mod->apply($engine, $config);
        }

        return $engine->projectorRegistry
            ->getProjectorFor($engine->list, $engine->context)
            ->project($engine->list, $engine->context);
    }

    /**
     * @api
     */
    public function addMod(string $modType, array $config): self
    {
        $this->mods[] = [
            'type' => $modType,
            'config' => $config,
        ];

        return $this;
    }

    /**
     * @api
     */
    public function setMod(string $name, string $modType, array $config): self
    {
        $this->mods[$name] = [
            'type' => $modType,
            'config' => $config,
        ];

        return $this;
    }

    public function unsetMod(string $name): self
    {
        unset($this->mods[$name]);
        return $this;
    }

    /**
     * @api
     */
    public function clearMods(): self
    {
        $this->mods = [];
        return $this;
    }

    public function with(?ContextInterface $context = null, ?ListSpecification $list = null, ?array $mods = null): self
    {
        return new self(
            engineModRegistry: $this->engineModRegistry,
            projectorRegistry: $this->projectorRegistry,
            context: $context ?? clone $this->context,
            list: $list ?? clone $this->list,
            mods: $mods ?? $this->mods,
        );
    }

    public function clone(): self
    {
        return clone $this;
    }

    public function __clone(): void
    {
        $this->context = clone $this->context;
        $this->list = clone $this->list;
    }
}