<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Engine\Mod;

use HeimrichHannot\FlareBundle\Engine\Intent\PaginatedIntentInterface;
use HeimrichHannot\FlareBundle\Engine\Engine;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PageParamMod extends AbstractMod
{
    public static function getType(): string
    {
        return 'page_param';
    }

    public function __invoke(Engine $engine, array $options): void
    {
        $intent = $engine->getIntent();

        if (!$intent instanceof PaginatedIntentInterface) {
            return;
        }

        $intent->setPaginatorQueryParameter($options['param'] ?? null);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('param')
            ->required()
            ->allowedTypes('string')
            ->normalize(static function (OptionsResolver $resolver, string $value): string {
                if (!$value = (string) \preg_replace('/[^\w-]/', '', \trim($value))) {
                    throw new \InvalidArgumentException('Parameter name cannot be empty and my only contain alphanumeric characters.');
                }

                return $value;
            });
    }
}
