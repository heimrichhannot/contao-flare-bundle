<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\ContentModel as ContaoContentModel;

/**
 * Reads and writes content elements from the database.
 *
 * @proxy Contao\ContentModel
 *
 * @property string $flare_dcMultilingualDisplay
 * @property string $flare_formName
 * @property int    $flare_itemsPerPage
 * @property ?int   $flare_jumpTo
 * @property int    $flare_list
 */
class ContentModel extends ContaoContentModel
{
}