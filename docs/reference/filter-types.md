# Built-in Filter Types

Filter types are reusable query-fragment builders in the namespace `HeimrichHannot\FlareBundle\Filter\Type\`.
They are consumed by filter elements (via `FilterBuilderInterface::add()`) — including
[inline filters](../dev/filter-elements/index.md#9-inline-filters-without-a-service) added
programmatically — see the [filter types guide](../dev/filter-types.md).

Options are validated against each type's `configureOptions()` schema. Required options have no default.

| Class | Options | Purpose |
| :---- | :------ | :------ |
| `ArchiveFilterType` | `field` (string, default `pid`), `parent_ids` (array, **required**) | Constrains records to a set of parent/archive IDs. |
| `BelongsToRelationFilterType` | `field_pid` (string, **required**), `field_dynamic_ptable` (?string, default `null`), `whitelist` (array, default `[]`), `parent_groups` (array, default `[]`), `submitted_data` (?array, default `null`) | Filters by a belongs-to (parent) relation, optionally with a dynamic `ptable`. |
| `BooleanFilterType` | `field` (string, **required**), `value` (bool, **required**) | Matches a boolean/checkbox column against an expected value. |
| `CalendarCurrentFilterType` | `start` (int, **required**), `stop` (int, **required**), `has_extended_events` (bool, default `false`) | Constrains calendar events to a time window. |
| `DateRangeFilterType` | `field` (string, **required**), `from` (?`\DateTimeInterface`, default `null`), `to` (?`\DateTimeInterface`, default `null`) | Matches a timestamp column against a date range. |
| `DcaSelectFilterType` | `field` (string, **required**), `selected` (array, **required**), `valid_options` (array, **required**), `is_multiple_dca_field` (bool, default `false`) | Matches a DCA select field against chosen options, incl. serialized multi-value fields. |
| `FieldValueChoiceFilterType` | `field` (string, **required**), `values` (array, **required**) | Matches a column against a set of concrete values. |
| `IntegerIdChoiceFilterType` | `field` (string, default `id`), `ids` (array, **required**) | Matches an integer ID column against a set of IDs. |
| `PublishedFilterType` | `published_field` (?string, default `null`), `start_field` (?string, default `null`), `stop_field` (?string, default `null`), `invert_published` (bool, default `false`), `now` (int, **required**) | Standard Contao published/start/stop visibility check. |
| `SearchKeywordsFilterType` | `value` (string, **required**), `columns` (array, **required**) | Keyword search across one or more columns. |
| `SimpleEquationFilterType` | `operand_left` (string, **required**), `operator` (`SqlEquationOperator`\|string, **required**), `operand_right` (string\|int\|null, default `''`) | Generic `column <operator> value` equation (`=`, `!=`, `>`, `>=`, `<`, `<=`, `LIKE`, ...). |

To write your own, implement `FilterTypeInterface` (or extend `AbstractFilterType`) — registration is
automatic via the `huh.flare.filter_type` service tag. See the
[custom filter types guide](../dev/filter-types.md).
