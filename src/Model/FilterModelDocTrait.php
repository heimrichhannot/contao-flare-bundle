<?php

namespace HeimrichHannot\FlareBundle\Model;

/**
 * @property int    $id
 * @property int    $pid
 * @property int    $sorting
 * @property int    $tstamp
 * @property string $title
 * @property string $alias
 * @property bool   $published
 * @property bool   $intrinsic
 * @property bool   $isMandatory
 * @property bool   $isMultiple
 * @property bool   $isExpanded
 * @property bool   $hasEmptyOption
 * @property bool   $usePublished
 * @property bool   $useStart
 * @property bool   $useStop
 * @property bool   $invertPublished
 * @property string $formatLabel
 * @property string $formatLabelCustom
 * @property string $formatEmptyOption
 * @property string $formatEmptyOptionCustom
 * @property string $whichPtable
 * @property string $fieldPublished
 * @property string $fieldStart
 * @property string $fieldStop
 * @property string $fieldPid
 * @property string $fieldPtable
 * @property string $tablePtable
 * @property string $whitelistParents
 * @property string $groupWhitelistParents
 * @property string $preselect
 * @property string $equationOperator
 * @property string $equationLeft
 * @property string $equationRight
 */
trait FilterModelDocTrait
{
}