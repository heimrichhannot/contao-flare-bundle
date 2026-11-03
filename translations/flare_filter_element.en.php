<?php

use HeimrichHannot\FlareBundle\Filter\Element;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceFilterElement;

return [
    Element\ArchiveFilterElement::TYPE => 'Archive',
    Element\BelongsToRelationFilterElement::TYPE => 'Relation: Belongs to',
    Element\BooleanFilterElement::TYPE => 'Boolean property value',
    Element\CalendarCurrentFilterElement::TYPE => 'Calendar time window',
    Element\DateRangeFilterElement::TYPE => 'Date range',
    Element\DcaSelectFieldFilterElement::TYPE => 'DCA field options selection',
    Element\FieldValueChoiceFilterElement::TYPE => 'DCA field value selection (beta)',
    Element\PublishedFilterElement::TYPE => 'Published',
    Element\SimpleEquationFilterElement::TYPE => 'Simple equation',
    Element\SearchKeywordsFilterElement::TYPE => 'Keyword search',

    CodefogTagsChoiceFilterElement::TYPE => 'Tags [codefog/tags-bundle]',
];
