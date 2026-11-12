---
title: Enriching Entries
sidebar_position: 8
---

# Enriching Entries

List and reader templates receive **entries** (`HeimrichHannot\FlareBundle\List\Entry\Entry`): the row as the list
loaded it, plus the values that event listeners attach to it. Anything a template needs beyond the row — a related
record, a list of child records, a computed value — is attached by a listener on the entries event, and templates
read it like a column: `entry.archive.title`. See [Templating / Entries](../templating.mdx#entries) for the template
side.

One listener serves the list and the reader: both dispatch the same event.

## 1. The Entries Event

[`ListDriverEntriesEvent`](./events.md#listdriverentriesevent) is dispatched once per non-empty collection of
entries, before the collection is sealed:

- the entries of a list page, on the first access to `flare_list.entries`;
- a reader's entry, as a collection of one, when the reader is resolved;
- the members of a lookup (`flare_reader.lookup()`, or the lookup in a listener, see
  [Computed Similarity](#4-computed-similarity)).

It is never dispatched for an empty collection — a failed list, an invalid submission, an empty page, a lookup without
members — so a listener never runs against a failed list. Listen to the named event
`flare.list_driver.{type}.entries` to serve one list type, e.g. `flare.list_driver.flare_news.entries`, or to the
event class for all lists. The event carries `list` (the `ListCompound`: `type`, `dc`, `config`, and `source` to
target a single list, e.g. `tl_flare_list.5`), `intent` (`InteractiveIntent` for a list page, `ValidationIntent` for
the reader and lookups) and `entries` (the `EntryCollection`).

After the event, the collection and its entries are **sealed**: attach values inside the listener only — any
`attach()` afterwards throws a `SealedEntriesError`. Templates and the breadcrumb therefore see one fixed set of
values.

## 2. A Related Record (To-One)

`$event->attach($name, $load)` attaches a value to every entry of the collection in two phases:

```php
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener('flare.list_driver.flare_news.entries')]
final readonly class NewsArchiveListener
{
    public function __construct(private Connection $connection) {}

    public function __invoke(ListDriverEntriesEvent $event): void
    {
        // phase 1 runs once per collection, on the first access to `archive` on any entry
        $event->attach('archive', function (EntryCollection $entries): \Closure {
            $archives = $this->connection->fetchAllAssociativeIndexed(
                'SELECT id, title, jumpTo FROM tl_news_archive WHERE id IN (?)',
                [$entries->column('pid')],
                [ArrayParameterType::INTEGER],
            );

            // phase 2 picks one entry's value; memoized per entry
            return static fn (Entry $entry): ?array => $archives[$entry->pid] ?? null;
        });
    }
}
```

```twig
{% if entry.archive %}<p>{{ entry.archive.title }}</p>{% endif %}
```

- **Phase 1** loads the data for the whole collection — one query per list page, not one per entry. It runs lazily:
  only when something reads `archive` on one of the collection's entries; a template that never reads it costs no
  query. It receives the collection (and the list's lookup as a second argument, see below) and returns the picker.
- **Phase 2**, the picker, maps one entry to its value. It runs on the first read per entry; the value is memoized.
- `$entries->column('pid')` returns the distinct, non-empty values of a column across the collection; rows without
  that column are skipped.
- In PHP, `$entry->pid` reads the `pid` column (columns, then attachments, as in Twig; an unknown name throws an
  `UndefinedEntryKeyError`); `$entry->row` is the row array, `$entry->meta->modelRef` the record (`table`, `id`) and
  `$entry->to()` the reader URL.

`ArrayParameterType` needs DBAL 3.6 or later; the examples on this page use it for brevity.

:::warning[Names never collide silently]
`attach()` throws an `EntryNameCollisionError` for a reserved name (`to`, `row`, `meta`), for a column of any row of
the collection, and for a name that is already attached — to the collection or to one of its entries. Pick names
that are no columns of the list's rows.
:::

## 3. Related Records (To-Many)

A to-many value works the same way: phase 1 loads all child records of the collection at once, the picker returns
an entry's list of them.

```php
#[AsEventListener('flare.list_driver.flare_news.entries')]
final readonly class NewsCommentsListener
{
    public function __construct(private Connection $connection) {}

    public function __invoke(ListDriverEntriesEvent $event): void
    {
        $event->attach('comments', function (EntryCollection $entries): \Closure {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT id, parent, name, date FROM tl_comments"
                . " WHERE source = 'tl_news' AND parent IN (?) AND published = '1' ORDER BY date",
                [$entries->column('id')],
                [ArrayParameterType::INTEGER],
            );

            $byParent = [];

            foreach ($rows as $row) {
                $byParent[$row['parent']][] = $row;
            }

            return static fn (Entry $entry): array => $byParent[$entry->id] ?? [];
        });
    }
}
```

```twig
<p>{{ entry.comments|length }} comments</p>
```

A relation that the table's DCA defines (`foreignKey` + `relation`) needs no listener: see
[`flare_related()`](#6-related-records-without-a-listener-flare_related).

## 4. Computed Similarity

"More like this" — same tags, same category — is a listener too. Its phase 1 determines candidate ids by its own
query and checks them with the **lookup** it is handed as the second argument; the picker returns each entry's
similar members:

```php
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;

#[AsEventListener('flare.list_driver.flare_news.entries')]
final readonly class SimilarNewsListener
{
    public function __construct(private Connection $connection) {}

    public function __invoke(ListDriverEntriesEvent $event): void
    {
        $event->attach('similar', function (EntryCollection $entries, EntryLookupInterface $lookup): \Closure {
            // candidates by our own rule: the newest news of the same archives
            $candidates = $this->connection->fetchFirstColumn(
                'SELECT id FROM tl_news WHERE pid IN (?) ORDER BY date DESC LIMIT 50',
                [$entries->column('pid')],
                [ArrayParameterType::INTEGER],
            );

            // membership: only entries of this list, by the reader's rules, in one query
            $members = $lookup->lookup($candidates);

            return static function (Entry $entry) use ($members): array {
                $similar = [];

                foreach ($members as $member) {
                    if ((int) $member->pid === (int) $entry->pid && (int) $member->id !== (int) $entry->id) {
                        $similar[] = $member;
                    }
                }

                return \array_slice($similar, 0, 3);
            };
        });
    }
}
```

```twig
{% for similar in entry.similar %}
    <a href="{{ similar.to }}">{{ similar.headline }}</a>
{% endfor %}
```

The lookup is the one behind `flare_reader.lookup()` (see
[Templating / Linking Other Entries](../templating.mdx#linking-other-entries)):

- It takes ids of the list's own table — an int, a numeric string or a list of those — and returns, for a list, an
  `EntryCollection` of the members only, in the given order; for one id the entry or `null`. An empty list returns an
  empty collection without a query.
- Membership follows the reader's rules: the list's filters with their fixed values, never submitted form values. A
  lookup in a list view can therefore return entries the current form filter hides — the links lead to the reader.
- Its result is a sealed collection of its own: its entries link to the reader (`.to`), its members tag the response
  (`contao.db.<table>.<id>`), and it dispatches its own entries event, so the members carry the same attachments.
- There is no `$event->lookup()`: the lookup exists only inside phase 1, which runs after the event. A lookup's own
  entries event only registers lazy loaders again, so there is no eager recursion — `entry.similar[0].similar` runs a
  query only when a template reads it.

## 5. Cheap Per-Entry Values

A value that needs no query is attached on the entry itself:

```php
public function __invoke(ListDriverEntriesEvent $event): void
{
    foreach ($event->entries as $entry) {
        $entry->attach('isNew', (int) $entry->date > \strtotime('-7 days'));
    }
}
```

`Entry::attach($name, $value)` takes a value, or a `\Closure(Entry): mixed` that runs once on the first read and is
memoized. A `\Closure` always means "lazy": to attach a closure as the value itself, wrap it in another closure. Its
collision rules concern this entry only: it throws an `EntryNameCollisionError` for a reserved name (`to`, `row`,
`meta`), a column of the entry's own row, a name already attached to this entry and a name its collection attaches.
The same name can therefore be attached to every entry, as `isNew` above.

## 6. Related Records Without a Listener: `flare_related()`

For a relation the table's DCA defines, `flare_related(entry, column)` returns the related record like Contao's
`Model::getRelated()` — a model for a to-one relation, a model collection for a to-many relation:

```twig
<p>{{ flare_related(entry, 'pid').title }}</p>
```

It returns the **raw relation**: there is no membership check, for any target table — the list's own table included
(e.g. a parent record in a tree table), since a reader may need records of its table that are not in its list. When
you need a member of the list or a reader link, use the lookup: `flare_reader.lookup()` in a reader template; in a
list, use the lookup a listener's phase 1 receives.

## 7. Cache Tags for Loaded Data

The reader tags the response with its entry's record, and a lookup with its members'
(`contao.db.<table>.<id>`). Data a listener loads from other tables is not tagged; tag it with Contao's
`EntityCacheTags` service, so that a cached page is invalidated when that data changes. In the archive example:

```php
use Contao\CoreBundle\Cache\EntityCacheTags;

public function __construct(
    private Connection $connection,
    private EntityCacheTags $entityCacheTags,
) {}

// in phase 1, after loading $archives
$this->entityCacheTags->tagWith(\array_map(
    static fn (int|string $id): string => 'contao.db.tl_news_archive.' . $id,
    \array_keys($archives),
));
```

`tagWith()` accepts plain cache tags as strings, besides models and entities; it does nothing without response
tagging. Tagging inside phase 1 tags exactly the pages that read the value.

## 8. Contao APIs That Need a Model

Templates and listeners work with the row; Contao models are made only where a Contao API requires one, e.g.
`News::getSchemaOrgData()`. `HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory` is the one place an entry
becomes a model: `create(Entry $entry): ?Model` returns the instance Contao's model registry holds for the entry's
record, else a new model built from the entry's row (restricted to the table's columns; Contao registers it). It
returns `null` for a table without a model class and throws a `MissingModelRefError` for an entry without
`meta->modelRef` — check the table first. The bundled News schema.org listener does it like this (shortened):

```php
use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryModelFactory;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class NewsReaderSchemaOrgListener
{
    public function __construct(private EntryModelFactory $entryModelFactory) {}

    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        if ($event->entry->meta->modelRef?->table !== NewsModel::getTable()) {
            return;
        }

        $model = $this->entryModelFactory->create($event->entry);

        if ($model instanceof NewsModel) {
            $event->data = News::getSchemaOrgData($model);
        }
    }
}
```

Reusing the registry's instance is safe: the list's query has already confirmed that the record is a member of the
list.

## 9. Request Context

A reader page resolves its reader once per request, and the breadcrumb and the reader share the result. The breadcrumb
usually renders first — in the **main request**, before the reader's fragment sub-request exists. So the reader's
entries event, the lazy values attached in it and the `ReaderPageMetaEvent` may run while the breadcrumb renders.

Listeners must not depend on the fragment request: do not expect the reader element's request attributes in
`RequestStack::getCurrentRequest()`. Take what you need from the event (`list`, `intent`, `entries`) or from the main
request.
