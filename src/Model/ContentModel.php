<?php

namespace HeimrichHannot\FlareBundle\Model;

use Contao\ContentModel as ContaoContentModel;

/**
 * Reads and writes content elements from the database.
 *
 * @proxy Contao\ContentModel
 *
 * @property string $flare_formName
 * @property int $flare_itemsPerPage
 */
class ContentModel extends ContaoContentModel
{
}