<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Fixture;

use Contao\ContentModel;
use HeimrichHannot\FlareBundle\Model\ListModel;

/**
 * A content element with the given row and list, without a database or Contao's container (the model's constructor
 * needs them); every relation resolves to the list.
 */
final class ContentModelFixture extends ContentModel
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(array $row, private readonly ?ListModel $list = null)
    {
        $this->arrData = $row;
    }

    public function getRelated($strKey, array $arrOptions = []): ?ListModel
    {
        return $this->list;
    }
}
