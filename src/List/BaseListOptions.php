<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Framework-owned base schema and translation for every list. Applied unconditionally by
 * {@see Resolver\ListOptionsResolver} and {@see ListBuilder} before the list type's own
 * schema and transformers run, so framework consumers (page meta, comments, contexts)
 * can rely on these keys regardless of the type implementation.
 *
 * Canonical keys keep the tl_flare_list column names to preserve `{{list.*}}` tokens.
 */
final class BaseListOptions
{
    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('id')->default(null)->allowedTypes('int', 'null');
        $resolver->define('title')->default('')->allowedTypes('string');
        $resolver->define('published')->default(false)->allowedTypes('bool');
        $resolver->define('jumpToListView')->default(null)->allowedTypes('int', 'null');
        $resolver->define('jumpToReader')->default(null)->allowedTypes('int', 'null');
        $resolver->define('sortSettings')->default([])->allowedTypes('array');
        $resolver->define('metaTitleFormat')->default(null)->allowedTypes('string', 'null');
        $resolver->define('metaDescriptionFormat')->default(null)->allowedTypes('string', 'null');
        $resolver->define('metaRobotsFormat')->default(null)->allowedTypes('string', 'null');
        $resolver->define('fieldAutoItem')->default(null)->allowedTypes('string', 'null');
        $resolver->define('hasParent')->default(false)->allowedTypes('bool');
        $resolver->define('fieldPid')->default(null)->allowedTypes('string', 'null');
        $resolver->define('fieldPtable')->default(null)->allowedTypes('string', 'null');
        $resolver->define('tablePtable')->default(null)->allowedTypes('string', 'null');
        $resolver->define('whichPtable')->default('')->allowedTypes('string');
        $resolver->define('comments_enabled')->default(false)->allowedTypes('bool');
        $resolver->define('comments_sendNativeEmails')->default(false)->allowedTypes('bool');
        $resolver->define('dcMultilingual_display')->default(null)->allowedTypes('string', 'null');
        $resolver->define('genericPageMeta')->default(false)->allowedTypes('bool');
    }

    public static function transform(ListModel $model, ConfigBuilder $config): void
    {
        $config
            ->set('id', $model->id ? (int) $model->id : null)
            ->set('title', (string) $model->title)
            ->set('published', (bool) $model->published)
            ->set('jumpToListView', $model->jumpToListView ? (int) $model->jumpToListView : null)
            ->set('jumpToReader', $model->jumpToReader ? (int) $model->jumpToReader : null)
            ->set('sortSettings', StringUtil::deserialize($model->sortSettings, true))
            ->set('metaTitleFormat', $model->metaTitleFormat ?: null)
            ->set('metaDescriptionFormat', $model->metaDescriptionFormat ?: null)
            ->set('metaRobotsFormat', $model->metaRobotsFormat ?: null)
            ->set('fieldAutoItem', $model->fieldAutoItem ?: null)
            ->set('hasParent', (bool) $model->hasParent)
            ->set('fieldPid', $model->fieldPid ?: null)
            ->set('fieldPtable', $model->fieldPtable ?: null)
            ->set('tablePtable', $model->tablePtable ?: null)
            ->set('whichPtable', (string) $model->whichPtable)
            ->set('comments_enabled', (bool) $model->comments_enabled)
            ->set('comments_sendNativeEmails', (bool) $model->comments_sendNativeEmails)
            ->set('dcMultilingual_display', $model->dcMultilingual_display ?: null);
    }
}
