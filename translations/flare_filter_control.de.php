<?php

use HeimrichHannot\FlareBundle\Filter\Control;

return [
    Control\ChoiceFilterControl::TYPE => 'Auswahl',
    Control\CheckboxFilterControl::TYPE => 'Checkbox',
    Control\ChoiceBoolFilterControl::TYPE => 'Ja/Nein-Auswahl',
    Control\DateRangeFilterControl::TYPE => 'Datumsbereich',
    Control\KeywordsFilterControl::TYPE => 'Stichwortsuche',
];
