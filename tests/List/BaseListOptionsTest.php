<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\List\BaseListOptions;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;

final class BaseListOptionsTest extends TestCase
{
    public function testTransformsStoredRowToCanonicalValues(): void
    {
        $model = new ListModelStub([
            'id' => '5',
            'dc' => 'tl_news',
            'title' => 'My List',
            'published' => '1',
            'jumpToListView' => '',
            'jumpToReader' => '12',
            'sortSettings' => \serialize([['column' => 'title', 'direction' => 'ASC']]),
            'metaTitleFormat' => '',
            'fieldAutoItem' => 'alias',
            'hasParent' => '1',
            'fieldPid' => 'pid',
            'whichPtable' => 'auto',
        ]);

        BaseListOptions::transform($config = new ConfigBuilder(), $model);
        $all = $config->all();

        self::assertArrayNotHasKey('id', $all);
        self::assertArrayNotHasKey('published', $all);
        self::assertSame('tl_news', $all['dc']);
        self::assertSame('My List', $all['title']);
        self::assertNull($all['jumpToListView']);
        self::assertSame(12, $all['jumpToReader']);
        self::assertSame([['column' => 'title', 'direction' => 'ASC']], $all['sortSettings']);
        self::assertNull($all['metaTitleFormat']);
        self::assertSame('alias', $all['fieldAutoItem']);
        self::assertTrue($all['hasParent']);
        self::assertSame('pid', $all['fieldPid']);
        self::assertSame('auto', $all['whichPtable']);
        self::assertFalse($all['comments_enabled']);
    }

    public function testSchemaProvidesDefaultsForEmptyConfig(): void
    {
        $resolved = (new ListOptionsResolver(new SchemaResolver()))->resolve(null, []);

        self::assertSame('', $resolved['dc']);
        self::assertSame('', $resolved['title']);
        self::assertSame([], $resolved['sortSettings']);
        self::assertNull($resolved['metaTitleFormat']);
        self::assertSame('', $resolved['whichPtable']);
        self::assertFalse($resolved['genericPageMeta']);
    }

    public function testTransformedRowSatisfiesTheSchema(): void
    {
        $model = new ListModelStub(['id' => '3', 'title' => 'x', 'sortSettings' => '']);

        BaseListOptions::transform($config = new ConfigBuilder(), $model);

        $resolved = (new ListOptionsResolver(new SchemaResolver()))->resolve(null, $config->all());

        self::assertSame('x', $resolved['title']);
        self::assertSame([], $resolved['sortSettings']);
    }
}

class ListModelStub extends ListModel
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(array $row = [])
    {
        $this->arrData = $row;
    }
}
