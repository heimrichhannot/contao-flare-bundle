<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Inspection;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Event\FilterInspectionEvent;
use HeimrichHannot\FlareBundle\EventListener\Inspection\ExclusivePublishedFilterListener;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\List\Output;
use HeimrichHannot\FlareBundle\Tests\Contao\Inspection\Stub\OkElement;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExclusivePublishedFilterListenerTest extends TestCase
{
    public function testWarnsForAnExclusivePublishedFilterWithoutANonExclusiveOne(): void
    {
        $filter = self::published('a', [Output::ListView]);
        $other = self::filter(new OkElement(), 'b');

        $event = $this->dispatch($filter, self::list($filter, $other));

        self::assertSame(
            [['message' => 'translated filter.info.warning.exclusive_published', 'icon' => 'opacity']],
            $event->getWarnings(),
        );
    }

    public function testSilentWithANonExclusivePublishedFilter(): void
    {
        $filter = self::published('a', [Output::ListView]);

        $event = $this->dispatch($filter, self::list($filter, self::published('b')));

        self::assertSame([], $event->getWarnings());
    }

    public function testSilentForANonExclusivePublishedFilter(): void
    {
        $filter = self::published('a');

        self::assertSame([], $this->dispatch($filter, self::list($filter))->getWarnings());
    }

    public function testSilentForAnExclusiveFilterWithoutOutput(): void
    {
        $filter = self::published('a', []);

        self::assertSame([], $this->dispatch($filter, self::list($filter))->getWarnings());
    }

    public function testSilentForOtherElements(): void
    {
        $filter = self::filter(new OkElement(), 'a', [Output::ListView]);

        self::assertSame([], $this->dispatch($filter, self::list($filter))->getWarnings());
        self::assertSame([], $this->dispatch(null, self::list())->getWarnings(), 'a row that could not be built');
    }

    public function testSilentWithoutAList(): void
    {
        self::assertSame([], $this->dispatch(self::published('a', [Output::ListView]), null)->getWarnings());
    }

    private function dispatch(?FilterCompound $filter, ?ListCompound $list): FilterInspectionEvent
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id, array $parameters, ?string $domain): string => match ($domain) {
                'flare' => "translated {$id}",
                default => $id,
            });

        $event = new FilterInspectionEvent(new FilterModelFixture(['id' => 1]), $list, $filter, []);

        (new ExclusivePublishedFilterListener($translator))($event);

        return $event;
    }

    /**
     * @param list<Output>|null $outputs
     */
    private static function published(string $alias, ?array $outputs = null): FilterCompound
    {
        return self::filter(new PublishedFilterElement(), $alias, $outputs);
    }

    /**
     * @param list<Output>|null $outputs
     */
    private static function filter(
        FilterElementInterface $element,
        string                 $alias,
        ?array                 $outputs = null,
    ): FilterCompound {
        return new FilterCompound(
            element: new ElementBinding($element, 'stub', new ComponentConfig(), null),
            alias: $alias,
            outputs: $outputs,
        );
    }

    private static function list(FilterCompound ...$filters): ListCompound
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $keyed = [];

        foreach ($filters as $filter) {
            $keyed[$filter->alias] = $filter;
        }

        return new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test', filters: $keyed);
    }
}
