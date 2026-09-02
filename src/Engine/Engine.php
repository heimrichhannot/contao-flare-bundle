<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\View\ViewInterface;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Registry\EngineModRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;

final class Engine
{
    public function __construct(
        private readonly EngineModRegistry $engineModRegistry,
        private readonly ProjectorRegistry $projectorRegistry,
        private ContextInterface           $context,
        private ListSpec                   $list,
        private array                      $mods = [],
    ) {}

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getList(): ListSpec
    {
        return $this->list;
    }

    public function setList(ListSpec $list): self
    {
        $this->list = $list;

        return $this;
    }

    /**
     * @throws FlareException
     */
    public function createView(): ViewInterface
    {
        $engine = clone $this;

        foreach ($this->mods as $modConf)
        {
            ['type' => $type, 'config' => $config] = $modConf;

            $mod = $this->engineModRegistry->get($type)
                ?? throw new FlareException(
                    \sprintf('No FLARE engine mod registered with type "%s".', $type),
                    method: __METHOD__,
                );

            $mod->apply($engine, $config);
        }

        return $engine->projectorRegistry
            ->getProjectorFor($engine->list, $engine->context)
            ->project($engine->list, $engine->context);
    }

    /**
     * @api
     */
    public function addMod(string $modType, array $config = []): self
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

    public function with(?ContextInterface $context = null, ?ListSpec $list = null, ?array $mods = null): self
    {
        return new self(
            engineModRegistry: $this->engineModRegistry,
            projectorRegistry: $this->projectorRegistry,
            context: $context ?? clone $this->context,
            list: $list ?? $this->list,
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
    }
}
