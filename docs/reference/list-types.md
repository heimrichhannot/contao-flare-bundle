# Built-in List Types

| Type | Backend label | Data container | Driver class |
|---|---|---|---|
| `flare_generic_dc` | Data Container | user-selected | `HeimrichHannot\FlareBundle\List\Driver\GenericDataContainerListDriver` |
| `flare_news` | News | `tl_news` | `HeimrichHannot\FlareBundle\List\Driver\NewsListDriver` |
| `flare_events` | Events | `tl_calendar_events` | `HeimrichHannot\FlareBundle\Integration\ContaoCalendar\ListDriver\EventsListDriver` |

## Notes

- **Data Container** (`flare_generic_dc`) is the universal list type: pick any DCA table in the
  backend configuration. Parent-table (`ptable`) relationships are inferred automatically where
  possible.
- **News** (`flare_news`) joins `tl_news_archive` and adds a `published` filter by default; adding
  your own published filter replaces the default one.
- **Events** (`flare_events`) is only available when `contao/calendar-bundle` is installed. It
  joins `tl_calendar` and also adds a default `published` filter. See
  [Integrations / Contao Calendar](../integrations/index.md#contao-calendar).

To create your own, see [Developers / Custom List Drivers](../dev/list-types/index.md).
