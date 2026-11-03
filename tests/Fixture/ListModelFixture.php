<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * A ListModel with the given row, without a database or Contao's container (the model's constructor and
 * setRow() need them).
 */
final class ListModelFixture extends ListModel
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(array $row)
    {
        $this->arrData = $row;
    }
}
