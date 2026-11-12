<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\ListEntry;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DcaExtractor;
use Contao\Model;
use Contao\Model\Registry;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Exception\MissingModelRefError;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\Tests\Fixture\TestModelFixture;

class EntryModelFactoryTest extends ContaoTestCase
{
    private int $instancesCreated = 0;

    public function testWithoutModelRefThrows(): void
    {
        $this->expectException(MissingModelRefError::class);
        $this->factory()->create(new Entry(['id' => 1]));
    }

    public function testWithoutRegisteredModelClassReturnsNull(): void
    {
        self::assertNull($this->factory(classLookupThrows: true)->create($this->entry()));
    }

    public function testARegisteredButMissingModelClassReturnsNull(): void
    {
        self::assertNull($this->factory(class: 'Contao\NoSuchModel')->create($this->entry()));
    }

    public function testAClassThatIsNoModelReturnsNull(): void
    {
        self::assertNull($this->factory(class: \stdClass::class)->create($this->entry()));
    }

    public function testReturnsTheRegistryInstance(): void
    {
        $registered = new TestModelFixture(['id' => 5]);

        self::assertSame($registered, $this->factory(registered: $registered)->create($this->entry()));
        self::assertSame(0, $this->instancesCreated);
    }

    public function testCreatesAndRegistersAModelFromTheTableColumnsOnly(): void
    {
        $factory = $this->factory();
        $entry = $this->entry(['id' => 5, 'title' => 'T', '_flare_event_group' => 'x', 'a__b' => 1]);

        $model = $factory->create($entry);
        self::assertInstanceOf(TestModelFixture::class, $model);
        self::assertSame(['id' => 5, 'title' => 'T'], $model->row());
        self::assertSame($model, $factory->create($entry));
        self::assertSame(1, $this->instancesCreated);
    }

    public function testTheIdSurvivesATableWithoutSqlFields(): void
    {
        $model = $this->factory(fields: [])->create($this->entry(['id' => 5, 'title' => 'T']));

        self::assertSame(['id' => 5], $model?->row());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entry(array $row = ['id' => 5]): Entry
    {
        $entry = EntryCollection::forTable('tl_test', [$row])->first();
        self::assertNotNull($entry);

        return $entry;
    }

    /**
     * @param ?class-string $registered
     * @param array<string, mixed> $fields
     */
    private function factory(
        bool $classLookupThrows = false,
        string $class = TestModelFixture::class,
        ?Model $registered = null,
        array $fields = ['id' => [], 'title' => []],
    ): EntryModelFactory {
        $this->instancesCreated = 0;
        $store = [];

        $modelAdapter = $this->mockAdapter(['getClassFromTable']);
        if ($classLookupThrows) {
            $modelAdapter->method('getClassFromTable')->willThrowException(new \RuntimeException('no model'));
        } else {
            $modelAdapter->method('getClassFromTable')->willReturn($class);
        }

        $registry = $this->createMock(Registry::class);
        $registry->method('fetch')->willReturnCallback(
            static function (string $table, $id) use ($registered, &$store): ?Model {
                return $store[$table . '.' . $id] ?? $registered;
            },
        );
        $registryAdapter = $this->mockAdapter(['getInstance']);
        $registryAdapter->method('getInstance')->willReturn($registry);

        $extractor = $this->createMock(DcaExtractor::class);
        $extractor->method('getFields')->willReturn($fields);
        $extractorAdapter = $this->mockAdapter(['getInstance']);
        $extractorAdapter->method('getInstance')->willReturn($extractor);

        $framework = $this->createMock(ContaoFramework::class);
        $framework->method('getAdapter')->willReturnCallback(
            static fn (string $class) => match ($class) {
                Model::class => $modelAdapter,
                Registry::class => $registryAdapter,
                DcaExtractor::class => $extractorAdapter,
            },
        );
        $framework->method('createInstance')->willReturnCallback(
            function (string $class, array $args) use (&$store): TestModelFixture {
                ++$this->instancesCreated;
                $model = new TestModelFixture($args[0]);
                $store['tl_test.' . $args[0]['id']] = $model;

                return $model;
            },
        );

        return new EntryModelFactory($framework);
    }
}
