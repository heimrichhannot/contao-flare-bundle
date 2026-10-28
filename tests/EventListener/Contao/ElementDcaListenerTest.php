<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Contao;

use HeimrichHannot\FlareBundle\EventListener\Contao\ElementDcaListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ElementDcaListenerTest extends TestCase
{
    public function testFilterStatesAreReportedOnGetRequestsOnly(): void
    {
        // The DCA loads before Contao saves the submitted form; a message added then would be flashed across the
        // redirect, reporting the state from before the save.
        self::assertTrue($this->reportsFilterStates(Request::create('/contao', 'GET')));
        self::assertFalse($this->reportsFilterStates(Request::create('/contao', 'POST')));
        self::assertFalse($this->reportsFilterStates(null));
    }

    private function reportsFilterStates(?Request $request): bool
    {
        $listener = (new \ReflectionClass(ElementDcaListener::class))->newInstanceWithoutConstructor();
        $requestStack = new RequestStack();

        if ($request !== null) {
            $requestStack->push($request);
        }

        (function () use ($requestStack): void {
            $this->requestStack = $requestStack;
        })->call($listener);

        return (fn (): bool => $this->reportsFilterStates())->call($listener);
    }
}
