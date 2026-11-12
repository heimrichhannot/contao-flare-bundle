<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Loader;

use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoader;
use HeimrichHannot\FlareBundle\Engine\Loader\ValidationLoaderConfig;
use HeimrichHannot\FlareBundle\Tests\Engine\EnginePipelineTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ValidationLoaderTest extends TestCase
{
    use EnginePipelineTrait;

    public function testTheAutoItemZeroIsLookedUp(): void
    {
        // The reader URL generator links an auto-item '0'; the reader must find it.
        $row = $this->loader([['id' => 1, 'alias' => '0']])->fetchEntryByAutoItem('0');

        self::assertSame(1, (int) ($row['id'] ?? 0));
    }

    public function testAnEmptyAutoItemIsAMissWithoutAQuery(): void
    {
        self::assertNull($this->loader([['id' => 1, 'alias' => '']])->fetchEntryByAutoItem(''));
        self::assertSame(0, $this->queries);
    }

    /**
     * A loader with the auto-item field `alias` over a list on `tl_test` with $rows.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function loader(array $rows): ValidationLoader
    {
        return $this->createLoaderFactory($this->createConnection($rows), new EventDispatcher())
            ->createValidationLoader(new ValidationLoaderConfig(
                list: $this->createList(),
                intent: new ValidationIntent(autoItemField: 'alias'),
                autoItemField: 'alias',
            ));
    }
}
