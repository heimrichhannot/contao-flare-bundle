<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DependencyInjection;

use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Util\Env;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class HeimrichHannotFlareExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('services.yaml');

        if (Env::hasContaoCalendar()) {
            $loader->load('integrations/contao_calendar.yaml');
        }

        if (Env::hasContaoComments()) {
            $loader->load('integrations/contao_comments.yaml');
        }

        if (Env::hasContaoNews()) {
            $loader->load('integrations/contao_news.yaml');
        }

        if (Env::hasCodefogTags()) {
            $loader->load('integrations/codefog_tags.yaml');
        }

        // if (Env::hasTerminal42ChangeLanguage()) {
        //     $loader->load('integrations/terminal42_changelanguage.yaml');
        // }

        $configuration = new Configuration();
        $flareConfig = $this->processConfiguration($configuration, $configs);

        $container->setParameter($this->getAlias(), $flareConfig);
        $container->setParameter($this->getAlias() . '.format_label_defaults', $flareConfig['format_label_defaults'] ?? []);

        $attributesForAutoconfiguration = [
            AsListType::class => AsListType::TAG,
            AsFilterElement::class => AsFilterElement::TAG,
        ];

        foreach ($attributesForAutoconfiguration as $attributeClass => $tag)
        {
            $container->registerAttributeForAutoconfiguration(
                $attributeClass,
                static function (ChildDefinition $definition, object $attribute) use ($tag): void {
                    $tagAttributes = \property_exists($attribute, 'attributes')
                        ? $attribute->attributes
                        : \get_object_vars($attribute);

                    $definition->addTag($tag, $tagAttributes);
                }
            );
        }
    }

    public function getAlias(): string
    {
        return 'huh_flare';
    }

    public function prepend(ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__) . '/../config'));
        $loader->load('config.yaml');
    }
}