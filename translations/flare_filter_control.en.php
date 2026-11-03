<?php

use HeimrichHannot\FlareBundle\Filter\Control;

return [
    Control\ChoiceFilterControl::TYPE => 'Choice',
    Control\CheckboxFilterControl::TYPE => 'Checkbox',
    Control\ChoiceBoolFilterControl::TYPE => 'Yes/No choice',
    Control\DateRangeFilterControl::TYPE => 'Date range',
    Control\KeywordsFilterControl::TYPE => 'Keyword search',
];
