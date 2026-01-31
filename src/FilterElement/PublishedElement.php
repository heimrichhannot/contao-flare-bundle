<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;

#[AsFilterElement(
    type: self::TYPE,
    palette: '{filter_legend},usePublished,useStart,useStop'
)]
class PublishedElement extends AbstractFilterElement
{
    public const TYPE = 'flare_published';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        if ($inv->filter->usePublished ?? true)
        {
            $publishedField = $qb->column($inv->filter->fieldPublished ?: 'published');
            $invertPublished = $inv->filter->invertPublished ?? false;
            $operator = $invertPublished ? 'neq' : 'eq';

            // "published = '1'" or "published != '1'"
            $qb->where($qb->expr()->{$operator}($publishedField, $this->connection->quote(1)));
        }

        $epsilon = $this->connection->quote('');
        $zero = $this->connection->quote(0);

        if ($inv->filter->useStart ?? true)
        {
            $startField = $qb->column($inv->filter->fieldStart ?: 'start');

            $qb->where("{$startField} = {$epsilon} OR {$startField} = {$zero} OR {$startField} <= :start")
                ->setParameter('start', \time());
        }

        if ($inv->filter->useStop ?? true)
        {
            $stopField = $qb->column($inv->filter->fieldStop ?: 'stop');

            $qb->where("{$stopField} = {$epsilon} OR {$stopField} = {$zero} OR {$stopField} >= :stop")
                ->setParameter('stop', \time());
        }
    }

    public static function define(
        string|false|null $published = null,
        string|false|null $start = null,
        string|false|null $stop = null,
        bool|null $invertPublished = null,
    ): FilterDefinition {
        $published ??= 'published';
        $start ??= 'start';
        $stop ??= 'stop';
        $invertPublished ??= false;

        $definition = new FilterDefinition(
            type: static::TYPE,
            title: 'Is Published',
            intrinsic: true,
        );

        if ($published) {
            $definition->usePublished = true;
            $definition->fieldPublished = $published;
            $definition->invertPublished = $invertPublished;
        }

        if ($start) {
            $definition->useStart = true;
            $definition->fieldStart = $start;
        }

        if ($stop) {
            $definition->useStop = true;
            $definition->fieldStop = $stop;
        }

        return $definition;
    }
}