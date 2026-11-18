# Built-in Filter Elements

All classes live in `HeimrichHannot\FlareBundle\Filter\Element\` unless noted otherwise and are named after
their type with a `FilterElement` suffix (e.g. `flare_bool` → `BooleanFilterElement`).
"Form controls" are the Symfony form fields of the [filter controls](../dev/filter-controls.md) an editor can choose for the element; elements without a value class (or with the control left blank) are applied without a user-facing form field (intrinsic filters).

| Type | Backend label | Form controls | Purpose |
|---|---|---|---|
| `flare_archive` | Archive | `ChoiceType` | Filter by parent archive (e.g. news archives). Without a control the value (any parent archive) is the restriction; with one, the parent whitelist provides the options. |
| `flare_bool` | Boolean property value | `CheckboxType` | Filter on a boolean column. |
| `flare_calendar_current` | Calendar time window | 2× `DateType` (from/to) | Time-window filter for calendar events, recurring-aware; the configured period bounds the list, a value narrows within it (see [below](#calendar-time-window)). |
| `flare_dateRange` | Date range | 2× `DateType` (from/to) | Filter a date column by from/to values; either field can be disabled via the `from_enabled`/`to_enabled` config options. |
| `flare_dcaSelectField` | DCA field options selection | `ChoiceType` | Choices from a DCA select field's options. |
| `flare_equation_simple` | Simple equation | — | Intrinsic `column <operator> value` condition. |
| `flare_fieldValueChoice` | DCA field value selection (beta) | `ChoiceType` | Choices from the distinct values of a column. |
| `flare_published` | Published | — | Contao publish state incl. start/stop dates. |
| `flare_relation_belongsTo` | Relation: Belongs to | — | Filter by a belongs-to (pid) relation, dynamic-ptable-aware. |
| `flare_search_keywords` | Keyword search | `TextType` | Keyword search across configured columns. |

:::note Known limitation of `flare_archive`

Without a control, on a list whose data container has a dynamic parent table (e.g. `tl_content`), the backend value
field lists every record of every parent table.

:::

Each element translates its configuration into calls to reusable
[predicates](./predicates.md), which build the actual query fragments.

## Apply exclusively

Every filter, of any type, has under **Expert Settings** the checkbox **Apply exclusively** (`exclusive`, off by
default) and, once it is ticked, the **Outputs** (`outputs`) the filter applies to. Today there is one output, the **List
view**; a sitemap and an export may follow.

| Setting | The filter applies |
|---|---|
| not exclusive | everywhere: it bounds the **whole list** — the list view, the reader, the breadcrumb and the lookup (`flare_reader.lookup()`) |
| exclusive, outputs ticked | only in those outputs; the reader, the breadcrumb and the lookup ignore it |
| exclusive, nothing ticked | nowhere; the backend marks it as ineffective |

So a list view can show less than its reader finds: a list of events that shows only what is coming, while the
reader still finds a past event, is one calendar filter exclusive to the list view. The list view's count follows
the list view. Every output is a subset of the whole list, so "reader" is no option to tick. A stored output this
version does not know is ignored — a filter left without a known output applies nowhere, never everywhere. A list
with a broken filter fails in every output, also when that filter is exclusive to another one.

A filter type can hide the setting by replacing the palette suffix (`DcaBuilderInterface::suffix()`); a setting
stored before still applies.

## Calendar time window

`flare_calendar_current` follows one rule, in every list it is part of:

- **The configured period is a bound.** A side applies when its mode (*Configure start date* / *Configure end
  date*) is set; a side without a mode, or with an empty date, is open. The modes: **Set custom date** (a date
  picker), **Date string (PHP)** (a `strtotime()` expression such as `last week thursday`) and the time spans
  (*Today*, *This week*, ...). Relative expressions are resolved each time the list is built.
- **A side is a point in time, not a day.** `today` is today 00:00: as the end it excludes an event that starts
  later today. Write `tomorrow` or `today 23:59` to include today.
- **The value narrows within the period.** A `from` before the start or a `to` after the end is ignored on that
  side; the date inputs of a control are limited to the configured period. Where the value and the period do not
  overlap, the filter matches nothing.
- **The value's role** is the same as for every filter: without a control it is a fixed narrowing, in the reader
  too (unless the filter is exclusive); with a control it is the preselection, which the visitor moves within the
  period and the reader never sees.
- **Unreadable text fails the list.** The backend rejects such text on save; a stored one makes the list fail and
  the filter is marked as broken.

The two purposes of a period are two settings — where both are wanted with different periods, two filters:

| Wanted | Filters |
|---|---|
| a hard bound | one, not exclusive |
| the list view shows what is coming, the reader finds everything | one, exclusive to the list view |
| a hard bound and a narrower section in the list view | two: one not exclusive, one exclusive to the list view |
| a preselection the visitor can move | one with a control; its value is the preselection |

The v0.1 setting *Limit the date selection* (`is_limited`) is gone: its behavior is now the rule. See
[Migrating from v0.1](../migrating-from-v0.1.md#the-calendar-filters-period).

## Integration filter elements

Available when [`codefog/tags-bundle`](../integrations/index.md#codefog-tags) is installed
(classes in `HeimrichHannot\FlareBundle\Integration\CodefogTags\FilterElement\`):

| Type | Backend label | Form controls | Purpose |
|---|---|---|---|
| `cfg_tags_choice` | Tags [codefog/tags-bundle] | `ChoiceType` | Filter by tags of the source field. |
| `cfg_tags_search` | — | — | Keyword search across tag names — **currently disabled** (under development, hidden from the backend selection). |

To create your own filter element, see
[Developers / Custom Filter Elements](../dev/filter-elements/index.md).
