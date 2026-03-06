<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Integration\CodefogTags\EventListener;

use Contao\Controller;
use HeimrichHannot\FlareBundle\Event\QueryBaseInitializedEvent;
use HeimrichHannot\FlareBundle\Query\JoinTypeEnum;
use HeimrichHannot\FlareBundle\Query\SqlJoinStruct;
use HeimrichHannot\FlareBundle\Query\TableAliasRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
readonly class RegisterTagsTablesListener
{
    public function __construct(
        private ParameterBagInterface $parameters,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(QueryBaseInitializedEvent $event): void
    {
        $table = $event->listSpecification->dc;
        $columns = $this->getCodefogTagsTargetColumns($table);
        if (!$columns || !\is_array($columns)) {
            return;
        }

        Controller::loadDataContainer($table);

        $fields = \array_filter(
            $GLOBALS['TL_DCA'][$table]['fields'] ?? [],
            static fn ($key) => \in_array($key, $columns, true),
            mode: \ARRAY_FILTER_USE_KEY
        );

        if (\count($fields) > 1) {
            $this->logger->warning("[FLARE] Multiple codefog tag fields are currently not supported (\"$table\").");
            return;
        }

        if (\count($fields) < 1) {
            $this->logger->warning("[FLARE] Codefog tags integration could not find a tags field on {$table}.");
            return;
        }

        $tableBase = \preg_replace('/^tl_/', '', $table);
        $cfgJoinTable = "tl_cfg_tag_{$tableBase}";
        $cfgJoinAlias = "codefog_tags_{$tableBase}_join";
        $cfgJoinColumn = "{$tableBase}_id";

        $cfgTagsAlias = "codefog_tags_{$tableBase}";

        $registry = $event->registry;
        $fromAlias = TableAliasRegistry::ALIAS_MAIN;

        $registry->registerJoin(new SqlJoinStruct(
            fromAlias: $fromAlias,
            joinType: JoinTypeEnum::LEFT,
            table: $cfgJoinTable,
            joinAlias: $cfgJoinAlias,
            condition: $registry->makeJoinOn($cfgJoinAlias, $cfgJoinColumn, $fromAlias, 'id'),
        ), hidden: true);

        $registry->registerJoin(new SqlJoinStruct(
            fromAlias: $cfgJoinAlias,
            joinType: JoinTypeEnum::LEFT,
            table: 'tl_cfg_tag',
            joinAlias: $cfgTagsAlias,
            condition: $registry->makeJoinOn($cfgTagsAlias, 'id', $cfgJoinAlias, 'cfg_tag_id'),
        ), attributes: [
            'codefog_tags_field' => \current($columns),
        ]);
    }

    /**
     * @return string[]|null Array of table columns or null if the table has no tags field.
     */
    private function getCodefogTagsTargetColumns(string $table): ?array
    {
        $targets = $this->parameters->get('flare_codefog_tags_targets');

        if (!\is_array($targets)) {
            throw new \RuntimeException(
                '[FLARE] Dependency injection error: CodefogTags integration loaded, '
                . 'but flare_codefog_tags_targets has not been configured'
            );
        }

        return $targets[$table] ?? null;
    }
}