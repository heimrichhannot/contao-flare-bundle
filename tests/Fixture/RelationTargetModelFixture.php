<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use Contao\Model;

/**
 * A Contao model of table "tl_flare_rel_target" with nothing overridden: Contao's constructor, registry and finders
 * run against the test's database.
 */
class RelationTargetModelFixture extends Model
{
    protected static $strTable = 'tl_flare_rel_target';
}
