<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class FilterElementLabelsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../translations';

    public function testTheLabelCataloguesUseTheNewKey(): void
    {
        foreach (['en' => 'Time window', 'de' => 'Zeitfenster'] as $locale => $label) {
            $labels = require self::ROOT . "/flare_filter_element.{$locale}.php";

            self::assertSame($label, $labels['flare_time_window'] ?? null, $locale);
            self::assertArrayNotHasKey('flare_calendar_current', $labels, $locale);
        }
    }

    public function testTheMessagesMovedToTimeWindow(): void
    {
        foreach (['en', 'de'] as $locale) {
            $errors = Yaml::parseFile(self::ROOT . "/flare.{$locale}.yaml")['errors'];

            self::assertArrayNotHasKey('calendar', $errors, $locale);
            self::assertIsArray($errors['time_window'] ?? null, $locale);

            foreach (['no_column', 'unknown_column', 'date_column', 'inverted_period'] as $key) {
                self::assertNotSame('', $errors['time_window'][$key] ?? '', "{$locale} {$key}");
                self::assertIsString($errors['time_window'][$key], "{$locale} {$key}");
            }

            foreach (['start', 'stop', 'recurring', 'recurrences', 'repeat_end'] as $role) {
                self::assertNotSame('', $errors['time_window']['role'][$role] ?? '', "{$locale} role {$role}");
                self::assertIsString($errors['time_window']['role'][$role], "{$locale} role {$role}");
            }
        }
    }
}
