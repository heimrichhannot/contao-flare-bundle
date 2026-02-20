<?php

use HeimrichHannot\FlareBundle\FilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsElement;

return [
    FilterElement\Relation\ArchiveElement::TYPE => 'Archive',
    FilterElement\Relation\BelongsToRelationElement::TYPE => 'Relation: Belongs to',
    FilterElement\BooleanElement::TYPE => 'Boolean property value',
    FilterElement\CalendarCurrentElement::TYPE => 'Calendar time window',
    FilterElement\DateRangeElement::TYPE => 'Date range',
    FilterElement\DcaSelectFieldElement::TYPE => 'DCA field options selection',
    FilterElement\FieldValueChoiceElement::TYPE => 'DCA field value selection (beta)',
    FilterElement\PublishedElement::TYPE => 'Published',
    FilterElement\SimpleEquationElement::TYPE => 'Simple equation',
    FilterElement\SearchKeywordsElement::TYPE => 'Keyword search',

    CodefogTagsElement::TYPE => 'Tags [codefog/tags-bundle]',
];
