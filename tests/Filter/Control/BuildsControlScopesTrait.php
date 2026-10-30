<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Control;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Filter\Control\AbstractFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilder;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Forms;

trait BuildsControlScopesTrait
{
    /**
     * @param array<string, mixed> $controlConfig Overrides over the control's option defaults.
     */
    private function scope(
        AbstractFilterControl   $control,
        array                   $controlConfig = [],
        ?FilterElementInterface $element = null,
    ): ControlScope {
        $resolver = new FilterOptionsResolver(new SchemaResolver());

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $element ??= new class implements FilterElementInterface {
            public function buildContext(
                FilterContextBuilderInterface $builder,
                ElementScope                  $scope,
                ?ValueInterface               $value,
            ): void {}
        };

        return new ControlScope(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test'),
            filter: new Filter(
                element: new ElementBinding($element, 'test_element', $resolver->resolve($element, [])),
                alias: 'test',
                control: new ControlBinding($control, 'test_control', $resolver->resolve($control, $controlConfig)),
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
        $transformers = new TransformerMap();
        $control->configureTransformers($transformers);

        $transformer = $transformers->find($model = new ControlFilterModelStub($row));
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
