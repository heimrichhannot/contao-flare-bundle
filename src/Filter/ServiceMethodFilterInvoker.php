<?php

namespace HeimrichHannot\FlareBundle\Filter;

use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;

final readonly class ServiceMethodFilterInvoker implements FilterInvokerInterface
{
    public function __construct(
        private object $service,
        private string $method,
    ) {
        if (!\method_exists($this->service, $this->method))
        {
            throw new \InvalidArgumentException(\sprintf(
                'Method "%s::%s" does not exist.',
                $this->service::class,
                $this->method,
            ));
        }
    }

    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $this->service->{$this->method}($inv, $qb);
    }
}