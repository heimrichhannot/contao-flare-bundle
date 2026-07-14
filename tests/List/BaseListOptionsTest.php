<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
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

        self::assertSame(5, $all['id']);
        self::assertSame('My List', $all['title']);
        self::assertTrue($all['published']);
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
        $resolved = (new ListOptionsResolver())->resolve(null, []);

        self::assertNull($resolved['id']);
        self::assertSame('', $resolved['title']);
        self::assertFalse($resolved['published']);
        self::assertSame([], $resolved['sortSettings']);
        self::assertNull($resolved['metaTitleFormat']);
        self::assertSame('', $resolved['whichPtable']);
        self::assertFalse($resolved['genericPageMeta']);
    }

    public function testTransformedRowSatisfiesTheSchema(): void
    {
        $model = new ListModelStub(['id' => '3', 'title' => 'x', 'sortSettings' => '']);

        BaseListOptions::transform($config = new ConfigBuilder(), $model);

        $resolved = (new ListOptionsResolver())->resolve(null, $config->all());

        self::assertSame(3, $resolved['id']);
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
