<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use HeimrichHannot\FlareBundle\Model\FilterModel;

/**
 * A FilterModel with the given row, without a database or Contao's container (the model's constructor and
 * setRow() need them).
 */
final class FilterModelFixture extends FilterModel
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(array $row)
    {
        $this->arrData = $row;
    }
}
