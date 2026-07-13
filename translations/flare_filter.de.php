<?php

use HeimrichHannot\FlareBundle\Filter\Element;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement as CodefogTagsElement;

return [
    Element\ArchiveElement::TYPE => 'Archiv',
    Element\BelongsToRelationElement::TYPE => 'Relation: Gehört zu',
    Element\BooleanElement::TYPE => 'Boolescher Eigenschaftswert',
    Element\CalendarCurrentFilterElement::TYPE => 'Kalender-Zeitfenster',
    Element\DateRangeElement::TYPE => 'Datumsbereich',
    Element\DcaSelectFieldElement::TYPE => 'DCA-Feld Optionsauswahl',
    Element\FieldValueChoiceElement::TYPE => 'DCA-Feld Feldwerte-Auswahl (beta)',
    Element\PublishedFilterElement::TYPE => 'Veröffentlicht',
    Element\SimpleEquationFilterElement::TYPE => 'Einfache Gleichung',
    Element\SearchKeywordsFilterElement::TYPE => 'Stichwortsuche',

    CodefogTagsElement\CodefogTagsChoiceFilterElement::TYPE => 'Tag-Auswahl [codefog/tags-bundle]',
    CodefogTagsElement\CodefogTagsSearchElement::TYPE => 'Tag-Suche [codefog/tags-bundle]',
];
