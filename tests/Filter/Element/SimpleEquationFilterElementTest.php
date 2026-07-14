<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SimpleEquationFilterElementTest extends TestCase
{
    public function testTransformsPopulatedModel(): void
    {
        $config = $this->transform([
            'intrinsic' => '1',
            'equationLeft' => 'pid',
            'equationOperator' => '=',
            'equationRight' => '42',
        ]);

        self::assertTrue($config['intrinsic']);
        self::assertSame('pid', $config['left']);
        self::assertSame(SqlEquationOperator::EQUALS, $config['operator']);
        self::assertSame('42', $config['right']);
    }

    public function testTransformsEmptyModelToDefaults(): void
    {
        $config = $this->transform([
            'intrinsic' => '',
            'equationLeft' => '',
            'equationOperator' => '',
            'equationRight' => null,
        ]);

        self::assertFalse($config['intrinsic']);
        self::assertNull($config['left']);
        self::assertNull($config['operator']);
        self::assertNull($config['right']);
    }

    public function testTransformSatisfiesTheElementSchema(): void
    {
        $element = new SimpleEquationFilterElement();

        $resolver = new OptionsResolver();
        $element->configureOptions($resolver);

        $resolved = $resolver->resolve($this->transform([
            'equationLeft' => 'id',
            'equationOperator' => '>',
        ]));

        self::assertSame('id', $resolved['left']);
        self::assertSame(SqlEquationOperator::GREATER_THAN, $resolved['operator']);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(array $row): array
    {
        $element = new SimpleEquationFilterElement();

        $transformers = new TransformerResolver();
        $element->configureTransformers($transformers);

        $transformer = $transformers->resolve($model = new FilterModelStub($row));
        self::assertNotNull($transformer);

        $transformer($config = new ConfigBuilder(), $model);

        return $config->all();
    }
}

final class FilterModelStub extends FilterModel
{
    public function __construct(array $row = [])
    {
        $this->arrData = $row;
    }
}
