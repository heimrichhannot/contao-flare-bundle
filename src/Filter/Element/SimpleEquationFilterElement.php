<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Element;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Type\SimpleEquationFilterType;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, intrinsicOnly: true, isTargeted: true)]
class SimpleEquationFilterElement extends AbstractFilterFilterElement
{
    public const TYPE = 'flare_equation_simple';

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('left')->default(null)->allowedTypes('string', 'null');
        $resolver->define('operator')->default(null)->allowedTypes(SqlEquationOperator::class, 'null');
        $resolver->define('right')->default(null);
    }

    public function configFromRow(array $row): array
    {
        $operator = $row['equationOperator'] ?? null;

        return [
            'intrinsic' => (bool) ($row['intrinsic'] ?? false),
            'left' => ($row['equationLeft'] ?? null) ?: null,
            'operator' => $operator ? SqlEquationOperator::match($operator) : null,
            'right' => $row['equationRight'] ?? null,
        ];
    }

    /**
     * @throws FilterException
     */
    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $data): void
    {
        $config = $context->config;

        if (!($operand = $config['left']) || !($op = $config['operator'])) {
            throw new FilterException('Invalid filter configuration.');
        }

        $builder->add(SimpleEquationFilterType::class, [
            'operand_left' => $operand,
            'operator' => $op,
            'operand_right' => $config['right'],
        ]);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $operatorValue = $context->filterModel?->equationOperator;
        $operator = $operatorValue ? SqlEquationOperator::match($operatorValue) : null;

        $dca->palette($operator?->isUnary()
            ? '{flare_simple_equation_legend},equationLeft,equationOperator'
            : '{flare_simple_equation_legend},equationLeft,equationOperator,equationRight');

        $dca->field('equationLeft')
            ->options(fn (): array => DcaHelper::getFieldOptions($context->getTargetTable()));
    }

    /**
     * @throws FlareException
     */
    public static function define(
        ?string              $equationLeft = null,
        ?SqlEquationOperator $equationOperator = null,
        mixed                $equationRight = null,
    ): Filter {
        if (!$equationLeft || !$equationOperator || (!$equationOperator->isUnary() && $equationRight === null)) {
            throw new FlareException('Invalid filter definition for SimpleEquationElement.');
        }

        return new Filter(
            element: static::TYPE,
            config: [
                'intrinsic' => true,
                'left' => $equationLeft,
                'operator' => $equationOperator,
                'right' => $equationRight,
            ],
        );
    }
}
