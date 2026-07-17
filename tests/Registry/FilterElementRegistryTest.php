<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Registry;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;

final class FilterElementRegistryTest extends TestCase
{
    public function testAddAndTypedAccessors(): void
    {
        $registry = new FilterElementRegistry();
        $element = new RegistryElementStub();
        $attribute = new AsFilterElement(type: 'choice', isTargeted: true);

        $registry->add($element, $attribute, 'choice');

        self::assertTrue($registry->has('choice'));
        self::assertSame($element, $registry->getService('choice'));
        self::assertTrue($registry->getAttribute('choice')?->isTargeted);
        self::assertFalse($registry->isInline('choice'));
        self::assertSame(['choice'], $registry->keys());
    }

    public function testGetTypesMatchesRegisteredInstanceOnly(): void
    {
        $registry = new FilterElementRegistry();
        $registered = new RegistryElementStub();
        $inline = new RegistryElementStub();

        $registry->add($registered, null, 'a');

        self::assertSame(['a'], $registry->getTypes($registered));
        self::assertSame([], $registry->getTypes($inline));
        self::assertSame(['a'], $registry->getTypes(RegistryElementStub::class));
    }

    public function testInlineRegistrationUsesClassNameAsType(): void
    {
        $registry = new FilterElementRegistry();
        $element = new RegistryElementStub();

        $registry->add($element);

        self::assertTrue($registry->isInline(RegistryElementStub::class));
        self::assertSame($element, $registry->getService(RegistryElementStub::class));
        self::assertSame([RegistryElementStub::class], $registry->getTypes($element));
    }
}

final class RegistryElementStub implements FilterElementInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void {}

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void {}
}
