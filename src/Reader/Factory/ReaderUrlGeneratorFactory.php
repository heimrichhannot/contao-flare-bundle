<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Reader\Factory;

use HeimrichHannot\FlareBundle\Reader\NullReaderUrlGenerator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGenerator;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ReaderUrlGeneratorFactory
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(?ReaderUrlConfig $config): ReaderUrlGeneratorInterface
    {
        if (null === $config) {
            return $this->getNullGenerator();
        }

        return new ReaderUrlGenerator(
            eventDispatcher: $this->eventDispatcher,
            config: $config,
        );
    }

    private function getNullGenerator(): ReaderUrlGeneratorInterface
    {
        static $instance;
        return $instance ??= new NullReaderUrlGenerator();
    }
}