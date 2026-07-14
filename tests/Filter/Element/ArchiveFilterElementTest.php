<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ArchiveFilterElementTest extends TestCase
{
    public function testTransformsPopulatedModel(): void
    {
        $config = $this->transform([
            'intrinsic' => '1',
            'whitelistParents' => \serialize(['3', '5', '5', '0']),
            'groupWhitelistParents' => \serialize([['table' => 'tl_news_archive', 'ids' => ['1'], 'label' => 'A']]),
            'useWhitelistForOptionsOnly' => '1',
            'formatLabel' => '%title%',
            'hasEmptyOption' => '1',
            'formatEmptyOption' => '',
            'isMandatory' => '',
            'isMultiple' => '1',
            'isExpanded' => '',
            'preselect' => \serialize(['7']),
        ]);

        self::assertTrue($config['intrinsic']);
        self::assertSame([3, 5], $config['whitelist_parents']);
        self::assertTrue($config['use_whitelist_for_options_only']);
        self::assertSame('%title%', $config['format_label']);
        self::assertTrue($config['has_empty_option']);
        self::assertNull($config['format_empty_option']);
        self::assertFalse($config['is_mandatory']);
        self::assertTrue($config['is_multiple']);
        self::assertFalse($config['is_expanded']);
        self::assertSame(['7'], $config['preselect']);
    }

    public function testCollapsesCustomFormats(): void
    {
        $config = $this->transform([
            'formatLabel' => 'custom',
            'formatLabelCustom' => '%title% (%year%)',
            'formatEmptyOption' => 'custom',
            'formatEmptyOptionCustom' => '',
        ]);

        self::assertSame('%title% (%year%)', $config['format_label']);
        self::assertNull($config['format_empty_option']);
    }

    public function testTransformSatisfiesTheElementSchema(): void
    {
        $element = $this->createElement();

        $resolver = new OptionsResolver();
        $element->configureOptions($resolver);

        $resolved = $resolver->resolve($this->transform([
            'whitelistParents' => \serialize(['2']),
            'preselect' => '',
        ]));

        self::assertSame([2], $resolved['whitelist_parents']);
        self::assertSame([], $resolved['preselect']);
        self::assertFalse($resolved['intrinsic']);
    }

    private function createElement(): ArchiveFilterElement
    {
        // ChoicesBuilderFactory is readonly (not doublable); transformFilterModel() never touches it.
        return new ArchiveFilterElement(new ChoicesBuilderFactory(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ParameterBagInterface::class),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(array $row): array
    {
        $element = $this->createElement();

        $transformers = new TransformerResolver();
        $element->configureTransformers($transformers);

        $transformer = $transformers->resolve($model = new FilterModelStub($row));
        self::assertNotNull($transformer);

        $transformer($model, $config = new ConfigBuilder());

        return $config->all();
    }
}
