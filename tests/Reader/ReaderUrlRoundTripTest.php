<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Reader;

use Contao\Input;
use Contao\InputEncodingMode;
use Contao\PageModel;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlConfig;
use HeimrichHannot\FlareBundle\Reader\ReaderUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ReaderUrlRoundTripTest extends TestCase
{
    /**
     * The mocked PageModel::getAbsoluteUrl() is deprecated since Contao 5.3 (the only URL API on 4.13): its mock
     * triggers that deprecation.
     *
     * @group legacy
     * @dataProvider provideValues
     */
    public function testLinkRoutesBackToTheSameStoredValue(string $raw): void
    {
        $stored = Input::encodeInput($raw, InputEncodingMode::encodeAll); // how Contao stores backend input
        $routes = new RouteCollection();
        $routes->add('reader', new Route('/reader{!parameters}', ['parameters' => ''], ['parameters' => '(/.+?)?']));
        $context = new RequestContext('', 'GET', 'example.org', 'https');
        $page = $this->createMock(PageModel::class);
        $page->method('getAbsoluteUrl')->willReturnCallback(
            static fn (string $path): string => (new UrlGenerator($routes, $context))
                ->generate('reader', ['parameters' => $path], UrlGeneratorInterface::ABSOLUTE_URL)
        );
        $entry = (new EntryCollection([['id' => 1, 'alias' => $stored]]))->first();
        self::assertNotNull($entry);

        $url = (new ReaderUrlGenerator(new EventDispatcher(), new ReaderUrlConfig($page, 'alias')))->generate($entry);
        self::assertNotNull($url);
        $match = (new UrlMatcher($routes, $context))->match((string) \parse_url($url, \PHP_URL_PATH));
        $autoItem = \substr($match['parameters'], 1); // as Contao's InputEnhancer does

        // = Input::get('auto_item', false, true)
        self::assertSame($stored, Input::encodeInput($autoItem, InputEncodingMode::encodeAll));

        if (!\str_contains($raw, '%')) {
            self::assertStringNotContainsString('%25', $url, 'No double encoding.');
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValues(): iterable
    {
        yield 'space' => ['a b'];
        yield 'unicode' => ['Grüße'];
        yield 'quotes' => ["it's \"x\""];
        yield 'hash' => ['a#b'];
        yield 'percent' => ['100%'];
        yield 'ampersand' => ['a&b'];
    }
}
