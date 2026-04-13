<?php

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Engine;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\FilterElement\SimpleEquationElement;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SimpleEquationMod extends AbstractMod
{
    public static function getType(): string
    {
        return 'equation';
    }

    public function __invoke(Engine $engine, array $options): void
    {
        $filter = SimpleEquationElement::define(
            equationLeft: $options['operand1'],
            equationOperator: $options['operator'],
            equationRight: $options['operand2'],
        );

        $filters = $engine->getList()->getFilters();

        if ($name = $options['name']) {
            $filters->set($name, $filter);
            return;
        }

        $filters->add($filter);
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