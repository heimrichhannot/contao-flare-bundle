<?php

use HeimrichHannot\FlareBundle\FilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement as CodefogTagsElement;

return [
    FilterElement\ArchiveElement::TYPE => 'Archiv',
    FilterElement\BelongsToRelationElement::TYPE => 'Relation: Gehört zu',
    FilterElement\BooleanElement::TYPE => 'Boolescher Eigenschaftswert',
    FilterElement\CalendarCurrentElement::TYPE => 'Kalender-Zeitfenster',
    FilterElement\DateRangeElement::TYPE => 'Datumsbereich',
    FilterElement\DcaSelectFieldElement::TYPE => 'DCA-Feld Optionsauswahl',
    FilterElement\FieldValueChoiceElement::TYPE => 'DCA-Feld Feldwerte-Auswahl (beta)',
    FilterElement\PublishedElement::TYPE => 'Veröffentlicht',
    FilterElement\SimpleEquationElement::TYPE => 'Einfache Gleichung',
    FilterElement\SearchKeywordsElement::TYPE => 'Stichwortsuche',

    CodefogTagsElement\CodefogTagsChoiceElement::TYPE => 'Tag-Auswahl [codefog/tags-bundle]',
    CodefogTagsElement\CodefogTagsSearchElement::TYPE => 'Tag-Suche [codefog/tags-bundle]',
];
