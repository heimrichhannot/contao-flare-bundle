<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\OptionsResolver;

trait BuildsControlScopesTrait
{
    /**
     * @param array<string, mixed> $controlConfig Overrides over the control's option defaults.
     */
    private function scope(
        AbstractFilterControl $control,
        array $controlConfig = [],
        ?FilterElementInterface $element = null,
    ): FilterScope {
        $resolver = new OptionsResolver();
        $control->configureOptions($resolver);

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $element ??= new class implements FilterElementInterface {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
        };

        return new FilterScope(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test'),
            filter: new Filter(
                element: $element,
                type: 'test_element',
                alias: 'test',
                control: $control,
                controlConfig: $resolver->resolve($controlConfig),
            ),
            engineContext: new ConfigurationContext(),
        );
    }

    private function newFormBuilder(): FilterFormBuilder
    {
        return new FilterFormBuilder('test', null, new EventDispatcher(), Forms::createFormFactory());
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AbstractFilterControl $control, array $row): array
    {
        $transformers = new TransformerResolver();
        $control->configureTransformers($transformers);

        $transformer = $transformers->resolve($model = new ControlFilterModelStub($row));
        self::assertNotNull($transformer);

        $transformer($config = new ConfigBuilder(), $model);

        return $config->all();
    }
}

final class ControlFilterModelStub extends FilterModel
{
    public function __construct(array $row = [])
    {
        $this->arrData = $row;
    }
}
