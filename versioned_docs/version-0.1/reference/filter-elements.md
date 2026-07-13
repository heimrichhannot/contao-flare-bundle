# Built-in Filter Elements

All classes live in `HeimrichHannot\FlareBundle\FilterElement\` unless noted otherwise.
"Form type" is the Symfony form type rendered in the frontend filter form; elements without one
are applied without a user-facing form field (typically as intrinsic filters).

| Type | Backend label | Form type | Purpose |
|---|---|---|---|
| `flare_archive` | Archive | `ChoiceType` | Filter by parent archive (e.g. news archives). |
| `flare_bool` | Boolean property value | `CheckboxType` | Filter on a boolean column. |
| `flare_calendar_current` | Calendar time window | `DateRangeFilterType` | Time-window filter for calendar events, recurring-aware. |
| `flare_dateRange` | Date range | `DateRangeFilterType` | Filter a date column by from/to values. |
| `flare_dcaSelectField` | DCA field options selection | `ChoiceType` | Choices from a DCA select field's options. |
| `flare_equation_simple` | Simple equation | — | Intrinsic `column <operator> value` condition. |
| `flare_fieldValueChoice` | DCA field value selection (beta) | `ChoiceType` | Choices from the distinct values of a column. |
| `flare_published` | Published | — | Contao publish state incl. start/stop dates. |
| `flare_relation_belongsTo` | Relation: Belongs to | — | Filter by a belongs-to (pid) relation, dynamic-ptable-aware. |
| `flare_search_keywords` | Keyword search | `TextType` | Keyword search across configured columns. |

## Integration filter elements

Available when [`codefog/tags-bundle`](../integrations/index.md#codefog-tags) is installed
(classes in `HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\`):

| Type | Backend label | Form type | Purpose |
|---|---|---|---|
| `cfg_tags_choice` | Tags [codefog/tags-bundle] | `ChoiceType` | Filter by tags of the source field. |
| `cfg_tags_search` | — | `SearchType` | Keyword search across tag names. |

To create your own filter element, see
[Developers / Custom Filter Elements](../dev/filter-elements/index.md).
