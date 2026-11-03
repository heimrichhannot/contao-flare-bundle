<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use Contao\Config;
use Contao\DcaLoader;
use Contao\System;
use Symfony\Component\DependencyInjection\Container;

/**
 * Serves a data container array to Contao's static DCA and language loaders without booting the
 * framework: the table is marked as already loaded, so `Controller::loadDataContainer()` and
 * `Controller::loadLanguageFile()` return early and the code under test reads `$GLOBALS['TL_DCA']`.
 * The loaders only need a container that holds `Contao\Config`; the previous container is restored.
 */
trait StubsContaoDcaTrait
{
    /** @var list<string> */
    private array $stubbedDcaTables = [];
    private bool $containerStubbed = false;
    private ?object $previousContainer = null;

    /**
     * @param array<string, mixed> $dca
     */
    protected function stubDca(string $table, array $dca): void
    {
        if (!$this->containerStubbed)
        {
            $this->previousContainer = System::getContainer();

            $container = new Container();
            $container->set(Config::class, $this->createMock(Config::class));
            System::setContainer($container);

            $this->containerStubbed = true;
        }

        $GLOBALS['TL_DCA'][$table] = $dca;

        $loaded = new \ReflectionProperty(DcaLoader::class, 'arrLoaded');
        $value = $loaded->getValue();
        $value['dcaFiles'][$table] = true;
        $loaded->setValue(null, $value);

        $languageFiles = new \ReflectionProperty(System::class, 'arrLanguageFiles');
        $value = $languageFiles->getValue();
        $value[$table] = ['en' => true];
        $languageFiles->setValue(null, $value);

        $this->stubbedDcaTables[] = $table;
    }

    /**
     * @after
     */
    protected function removeStubbedDcas(): void
    {
        $loaded = new \ReflectionProperty(DcaLoader::class, 'arrLoaded');
        $languageFiles = new \ReflectionProperty(System::class, 'arrLanguageFiles');

        $loadedValue = $loaded->getValue();
        $languageValue = $languageFiles->getValue();

        foreach ($this->stubbedDcaTables as $table)
        {
            unset($GLOBALS['TL_DCA'][$table], $loadedValue['dcaFiles'][$table], $languageValue[$table]);
        }

        $loaded->setValue(null, $loadedValue);
        $languageFiles->setValue(null, $languageValue);

        $this->stubbedDcaTables = [];

        if ($this->containerStubbed)
        {
            (new \ReflectionProperty(System::class, 'objContainer'))->setValue(null, $this->previousContainer);

            $this->containerStubbed = false;
            $this->previousContainer = null;
        }
    }
}
