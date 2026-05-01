<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\FilterElement;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\FilterElement\AbstractFilterElement;
use HeimrichHannot\FlareBundle\FilterElement\FilterElementContext;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Registry\Descriptor\FilterElementDescriptor;
use HeimrichHannot\FlareBundle\Specification\ConfiguredFilter;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

final class AbstractFilterElementTest extends TestCase
{
    public function testIntrinsicFiltersDoNotAttachFormFields(): void
    {
        $element = new TestFilterElement();
        $builder = new RecordingFilterFormBuilder();

        $element->buildForm($builder, $this->createContext(new ConfiguredFilter(
            type: 'test',
            intrinsic: true,
            alias: 'field',
        )));

        self::assertSame([], $builder->added);
    }

    public function testNonIntrinsicFiltersAttachFormFields(): void
    {
        $element = new TestFilterElement();
        $builder = new RecordingFilterFormBuilder();
        $filter = new ConfiguredFilter(
            type: 'test',
            intrinsic: false,
            alias: 'field',
        );

        $element->buildForm($builder, $this->createContext($filter));

        self::assertSame([$filter], $builder->added);
    }

    private function createContext(ConfiguredFilter $filter): FilterElementContext
    {
        return new FilterElementContext(
            list: new ListSpecification('test_list', 'tl_test'),
            filter: $filter,
            engineContext: new TestContext(),
            descriptor: new FilterElementDescriptor(new TestFilterElement(), formType: 'test_form'),
        );
    }
}

final class TestFilterElement extends AbstractFilterElement
{
}

final class RecordingFilterFormBuilder implements FilterFormBuilderInterface
{
    /**
     * @var ConfiguredFilter[]
     */
    public array $added = [];

    public function add(FilterElementContext $context, ?string $formType = null, array $options = []): static
    {
        $this->added[] = $context->filter;

        return $this;
    }

    public function getRootBuilder(): FormBuilderInterface
    {
        throw new \LogicException('Not used in this test.');
    }
}

final class TestContext implements ContextInterface
{
    public static function getContextType(): string
    {
        return 'test';
    }
}
