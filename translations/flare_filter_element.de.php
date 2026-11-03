<?php

use HeimrichHannot\FlareBundle\Filter\Element;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement as CodefogTagsElement;

return [
    Element\ArchiveFilterElement::TYPE => 'Archiv',
    Element\BelongsToRelationFilterElement::TYPE => 'Relation: Gehört zu',
    Element\BooleanFilterElement::TYPE => 'Boolescher Eigenschaftswert',
    Element\CalendarCurrentFilterElement::TYPE => 'Kalender-Zeitfenster',
    Element\DateRangeFilterElement::TYPE => 'Datumsbereich',
    Element\DcaSelectFieldFilterElement::TYPE => 'DCA-Feld Optionsauswahl',
    Element\FieldValueChoiceFilterElement::TYPE => 'DCA-Feld Feldwerte-Auswahl (beta)',
    Element\PublishedFilterElement::TYPE => 'Veröffentlicht',
    Element\SimpleEquationFilterElement::TYPE => 'Einfache Gleichung',
    Element\SearchKeywordsFilterElement::TYPE => 'Stichwortsuche',

    CodefogTagsElement\CodefogTagsChoiceFilterElement::TYPE => 'Tag-Auswahl [codefog/tags-bundle]',
    CodefogTagsElement\CodefogTagsSearchElement::TYPE => 'Tag-Suche [codefog/tags-bundle]',
];
