<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use Contao\Model;
use Contao\Model\Registry;

/**
 * A Contao model for table "tl_test" without a database. Not final, so tests can mock getRelated().
 */
class TestModelFixture extends Model
{
    protected static $strTable = 'tl_test';

    /** @param array<string, mixed> $row */
    public function __construct(array $row = [])
    {
        $this->arrData = $row;
    }

    /**
     * No-ops: Contao's versions read the DCA (unique fields) through the container; tests register fixtures in the
     * real Model\Registry themselves.
     */
    public function onRegister(Registry $registry): void
    {
    }

    public function onUnregister(Registry $registry): void
    {
    }
}
