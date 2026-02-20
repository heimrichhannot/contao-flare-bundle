<?php

use HeimrichHannot\FlareBundle\FilterElement;
use HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\CodefogTagsElement;

return [
    FilterElement\Relation\ArchiveElement::TYPE => 'Archiv',
    FilterElement\Relation\BelongsToRelationElement::TYPE => 'Relation: Gehört zu',
    FilterElement\BooleanElement::TYPE => 'Boolescher Eigenschaftswert',
    FilterElement\CalendarCurrentElement::TYPE => 'Kalender-Zeitfenster',
    FilterElement\DateRangeElement::TYPE => 'Datumsbereich',
    FilterElement\DcaSelectFieldElement::TYPE => 'DCA-Feld Optionsauswahl',
    FilterElement\FieldValueChoiceElement::TYPE => 'DCA-Feld Feldwerte-Auswahl (beta)',
    FilterElement\PublishedElement::TYPE => 'Veröffentlicht',
    FilterElement\SimpleEquationElement::TYPE => 'Einfache Gleichung',
    FilterElement\SearchKeywordsElement::TYPE => 'Stichwortsuche',

    CodefogTagsElement::TYPE => 'Tags [codefog/tags-bundle]',
];
