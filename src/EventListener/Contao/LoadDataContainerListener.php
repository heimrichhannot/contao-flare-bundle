<?php

namespace HeimrichHannot\FlareBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use HeimrichHannot\FlareBundle\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackContainerInterface;
use HeimrichHannot\FlareBundle\FlareCallback\FlareCallbackRegistry;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

#[AsHook('loadDataContainer')]
readonly class LoadDataContainerListener
{
    public function __construct(
        private FlareCallbackRegistry $registry,
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(string $table): void
    {
        if ($table !== 'tl_flare_filter' && $table !== 'tl_flare_list') {
            return;
        }

        if (!isset($GLOBALS['TL_DCA'][$table]['fields'])) {
            return;
        }

        if (!$id = Input::get('id')) {
            return;
        }

        $model = match ($table) {
            'tl_flare_filter' => FilterModel::findByPk($id),
            'tl_flare_list' => ListModel::findByPk($id),
        };

        if (!$model || !$model->type) {
            return;
        }

        $prefix = match ($table) {
            'tl_flare_filter' => 'filter.',
            'tl_flare_list' => 'list.',
        };

        $callbacks = $this->registry->get($prefix . $model->type) ?? [];

        if (empty($callbacks)) {
            return;
        }

        $class = match ($table) {
            'tl_flare_filter' => FilterContainer::class,
            'tl_flare_list' => ListContainer::class,
        };

        if (!\is_subclass_of($class, FlareCallbackContainerInterface::class)) {
            return;
        }

        $exclude = \array_fill_keys(['id', 'pid', 'tstamp', 'sorting', 'type', 'published', 'intrinsic'], true);

        $refFields = &$GLOBALS['TL_DCA'][$table]['fields'];

        foreach ($refFields as $field => &$definition)
        {
            if ($exclude[$field] ?? false) {
                continue;
            }

            if (!empty($callbacks["fields.$field.options"]))
                // bind options callback
            {
                $definition['options_callback'] = [$class, 'handleFieldOptions'];
            }

            if (!empty($callbacks["fields.$field.load"]))
                // bind load callback
            {
                if (!\is_array($definition['load_callback'] ?? null)) {
                    $definition['load_callback'] = [];
                }

                $definition['load_callback'][] = [$class, 'handleLoadField'];
            }

            if (!empty($callbacks["fields.$field.save"]))
                // bind save callback
            {
                if (!\is_array($definition['save_callback'] ?? null)) {
                    $definition['save_callback'] = [];
                }

                $definition['save_callback'][] = [$class, 'handleSaveField'];
            }
        }
    }
}