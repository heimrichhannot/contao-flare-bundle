<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Util\Str;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimpleEquationMod extends AbstractMod
{
    public function __construct(
        private readonly FilterFactory $filterFactory,
    ) {}

    public static function getType(): string
    {
        return 'equation';
    }

    public function __invoke(Engine $engine, array $options): void
    {
        $filter = $this->filterFactory->create(
            element: SimpleEquationFilterElement::TYPE,
            alias: $options['name'] ?: ('_.equation_' . Str::random(8)),
            config: [
                'intrinsic' => true,
                'left' => $options['operand1'],
                'operator' => $options['operator'],
                'right' => $options['operand2'],
            ],
        );

        $engine->setList($engine->getList()->withFilter($filter));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'operand1',
            'operator',
        ]);

        $resolver->setDefault('operand2', null);
        $resolver->setDefault('name', null);

        $resolver->setAllowedTypes('operand1', 'string');
        $resolver->setAllowedTypes('operator', [SqlEquationOperator::class, 'string']);
        $resolver->setAllowedTypes('operand2', ['null', 'string', 'int', 'string[]', 'int[]']);
        $resolver->setAllowedTypes('name', ['null', 'string']);

        $resolver->setNormalizer(
            'operator',
            static fn (OptionsResolver $resolver, SqlEquationOperator|string $operator): SqlEquationOperator =>
                SqlEquationOperator::match($operator)
                ?? throw new \InvalidArgumentException('Invalid equation operator provided')
        );
    }
}
