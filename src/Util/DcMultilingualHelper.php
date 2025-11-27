<?php

namespace HeimrichHannot\FlareBundle\Util;

use Contao\Controller;
use Contao\Database;
use Contao\DcaExtractor;

class DcMultilingualHelper
{
    public const DISPLAY_MULTILINGUAL = 'multi';
    public const DISPLAY_LOCALIZED = 'localized';
    public const DISPLAY_DEFAULT = self::DISPLAY_MULTILINGUAL;
    public const DISPLAY_OPTIONS = [
        self::DISPLAY_MULTILINGUAL,
        self::DISPLAY_LOCALIZED,
    ];

    public static function getPidColumn(string $table): string
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['langPid'] ?? 'langPid';
    }

    public static function getLangColumn(string $table): string
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['langColumnName'] ?? 'language';
    }

    public static function getFallbackLanguage(string $table): string|null
    {
        Controller::loadDataContainer($table);
        return $GLOBALS['TL_DCA'][$table]['config']['fallbackLang'] ?? null;
    }

    public static function getRegularFields(string $table): array
    {
        $extractor = DcaExtractor::getInstance($table);
        $tableColumns = Database::getInstance()->getFieldNames($table);

        return array_intersect($tableColumns, array_keys($extractor->getFields()));
    }

    public static function getTranslatableFields(string $table): array
    {
        Controller::loadDataContainer($table);

        $fields = [];
        $tableColumns = Database::getInstance()->getFieldNames($table);

        foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $field => $data) {
            if (!isset($data['eval']['translatableFor']) || !\in_array($field, $tableColumns, true)) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }
}