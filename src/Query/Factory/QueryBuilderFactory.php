<?php

namespace HeimrichHannot\FlareBundle\Query\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlQueryStruct;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class QueryBuilderFactory
{
    public function __construct(
        private ValidatorInterface $validator,
        private Connection $connection,
    ) {}

    public function create(SqlQueryStruct $struct): QueryBuilder
    {
        $errors = $this->validator->validate($struct);
        if ($errors->count() > 0) {
            throw new ValidationFailedException('Invalid query definition.', $errors);
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->from($struct->getFrom(), $struct->getFromAlias());

        foreach ($struct->getSelect() ?? [] as $select) {
            $qb->addSelect($select);
        }

        foreach ($struct->getJoins() as $join) {
            $joinMethod = match ($join->joinType) {
                JoinTypeEnum::INNER => $qb->innerJoin(...),
                JoinTypeEnum::LEFT => $qb->leftJoin(...),
                JoinTypeEnum::RIGHT => $qb->rightJoin(...),
            };
            $joinMethod($join->fromAlias, $join->table, $join->joinAlias, $join->condition);
        }

        // Where / Conditions
        if ($condition = $struct->getConditions()) {
            $qb->where($condition);
        }

        // Group By
        if ($groups = $struct->getGroupBy()) {
            $qb->groupBy(...$groups);
        }

        // Having
        if ($having = $struct->getHaving()) {
            $qb->having(...$having);
        }

        // Order By
        foreach ($struct->getOrderBy() ?? [] as $orderBy) {
            [$target, $direction] = $orderBy;
            $qb->addOrderBy($target, $direction);
        }

        // Limit / Offset
        if (!\is_null($limit = $struct->getLimit())) {
            $qb->setMaxResults($limit);
        }

        if (!\is_null($offset = $struct->getOffset())) {
            $qb->setFirstResult($offset);
        }

        $types = $struct->getTypes();
        foreach ($struct->getParams() as $key => $value) {
            $qb->setParameter($key, $value, $types[$key] ?? null);
        }

        return $qb;
    }
}