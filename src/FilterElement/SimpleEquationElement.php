<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\FilterElement;

use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterCallback;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\FilterType\SimpleEquationFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Util\DcaHelper;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[AsFilterElement(type: self::TYPE, isTargeted: true)]
class SimpleEquationElement extends AbstractFilterElement
{
    public const TYPE = 'flare_equation_simple';

    /**
     * @throws FilterException
     */
    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        if (!($operand = $inv->filter->equationLeft)
            || !$op = SqlEquationOperator::match($inv->filter->equationOperator))
        {
            throw new FilterException('Invalid filter configuration.');
        }

        $filter = new SimpleEquationFilterType();
        $resolver = new OptionsResolver();
        $filter->configureOptions($resolver);
        $options = $resolver->resolve([
            'operand_left' => $operand,
            'operator' => $op,
            'operand_right' => $inv->filter->equationRight,
        ]);

        $filter->buildQuery($qb, $options);
    }

    #[AsFilterCallback(self::TYPE, 'fields.equationLeft.options')]
    public function getEquationLeftOptions(string $targetTable): array
    {
        return DcaHelper::getFieldOptions($targetTable);
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $filterModel = $config->getFilterModel();

        if (SqlEquationOperator::match($filterModel?->equationOperator)?->isUnary()) {
            return '{flare_simple_equation_legend},equationLeft,equationOperator';
        }

        return '{flare_simple_equation_legend},equationLeft,equationOperator,equationRight';
    }

    public static function define(
        ?string              $equationLeft = null,
        ?SqlEquationOperator $equationOperator = null,
        mixed                $equationRight = null,
    ): FilterDefinition {
        $definition = new FilterDefinition(
            type: static::TYPE,
            intrinsic: true,
        );

        if (!$equationLeft || !$equationOperator || (!$equationOperator->isUnary() && $equationRight === null)) {
            throw new FlareException('Invalid filter definition for SimpleEquationElement.');
        }

        $definition->equationLeft = $equationLeft;
        $definition->equationOperator = $equationOperator->value;
        $definition->equationRight = $equationRight;

        return $definition;
    }
}