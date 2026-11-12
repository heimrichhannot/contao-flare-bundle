<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Twig;

use HeimrichHannot\FlareBundle\Twig\Extension\FlareExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class FlareExtensionTest extends TestCase
{
    public function testEnclosureFilesIsGone(): void
    {
        $functions = (new FlareExtension())->getFunctions();
        $names = \array_map(static fn (TwigFunction $f): string => $f->getName(), $functions);

        self::assertNotContains('flare_enclosure_files', $names);
        self::assertContains('flare_enclosure', $names);
    }
}
