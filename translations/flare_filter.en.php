<?php

use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsChoiceFilterElement;

return [
    \HeimrichHannot\FlareBundle\Filter\Element\ArchiveElement::TYPE => 'Archive',
    \HeimrichHannot\FlareBundle\Filter\Element\BelongsToRelationElement::TYPE => 'Relation: Belongs to',
    \HeimrichHannot\FlareBundle\Filter\Element\BooleanElement::TYPE => 'Boolean property value',
    \HeimrichHannot\FlareBundle\Filter\Element\CalendarCurrentFilterElement::TYPE => 'Calendar time window',
    \HeimrichHannot\FlareBundle\Filter\Element\DateRangeElement::TYPE => 'Date range',
    \HeimrichHannot\FlareBundle\Filter\Element\DcaSelectFieldElement::TYPE => 'DCA field options selection',
    \HeimrichHannot\FlareBundle\Filter\Element\FieldValueChoiceElement::TYPE => 'DCA field value selection (beta)',
    \HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement::TYPE => 'Published',
    \HeimrichHannot\FlareBundle\Filter\Element\SimpleEquationFilterElement::TYPE => 'Simple equation',
    \HeimrichHannot\FlareBundle\Filter\Element\SearchKeywordsFilterElement::TYPE => 'Keyword search',

    CodefogTagsChoiceFilterElement::TYPE => 'Tags [codefog/tags-bundle]',
];
