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
values. Sealing is the framework's job: never call `seal()` in a listener — every later listener's `attach()` would
throw.

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
- A phase 1 that throws runs again on the next read; nothing of the failed load is kept.
- **Never read the name being loaded.** A phase 1 that reads its own name on another entry of the collection throws
  a `RecursiveEntryLoadError`. On the entry being read — in phase 1, in the picker or in a lazy closure of
  `Entry::attach()` — PHP does not call `__get()` again for the same object and name: the read only emits an
  "Undefined property" warning and yields `null`, and that `null` is memoized as the value.
- `$entries->column('pid')` returns the distinct, non-empty values of a column across the collection; rows without
  that column are skipped.
- In PHP, `$entry->pid` reads the `pid` column (columns, then attachments, as in Twig; an unknown name throws an
  `UndefinedEntryKeyError`); `$entry->row` is the row array, `$entry->meta->modelRef` the record (`table`, `id`) —
  the physical record of the list's main table — and `$entry->to()` the reader URL.

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

            // membership: only entries of the whole list, by the reader's rules, in one query
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

- It takes ids of the list's own table — a positive int, its string (no sign or leading zeros) or a list of those — and returns, for a list, an
  `EntryCollection` of the members only, in the given order; for one id the entry or `null`. An empty list returns an
  empty collection without a query. `null`, `''` and `0` — an empty relation field — count as no id: alone they return
  `null`, in a list they are skipped.
- Membership follows the reader's rules: the whole list — the list's filters that are not
  [exclusive](../reference/filter-elements.md#apply-exclusively) — with their fixed values, never submitted form
  values. A lookup in a list view can therefore return entries the current form filter or a filter exclusive to the
  list view hides — the links lead to the reader. Filters an engine mod adds to the list view apply.
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

`Entry::attach($name, $value)` takes a value, or a `\Closure(Entry): mixed` that runs on the first read and is
memoized once it returns; a closure that throws runs again on the next read. A `\Closure` always means "lazy": to attach a closure as the value itself, wrap it in another closure. Its
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

`flare_related()` resolves the relation itself, the way `Model::getRelated()` resolves a lazy relation, but without a
model of the entry: the relation comes from the DCA of the entry's table (`meta->modelRef->table`), the value from the
entry's row (`entry.row`), the records from the finders of the related table's model class. The entry's own table
needs no model class. An empty value (`null`, `''`, `0`, `'0'`, or a column the row does not have) returns `null`
without a query. Where the result differs from calling `getRelated()` on the record's model, on purpose:

- **No parent model is consulted.** The row decides: a model of the record that Contao's registry holds — with
  another value in the column, a relation it has already loaded, or a class that overrides `getRelated()` — plays no
  part.
- **A serialized empty array** (`a:0:{}`) in a to-many column returns `null` without a query; Contao would query
  for it.
- **Errors instead of PHP's:** an `UndefinedRelationError` for a column without a relation, a related table without a
  model class, and a relation without a type or of an unsupported type (Contao knows `hasOne`, `belongsTo`,
  `hasMany` and `belongsToMany`).

As in Contao, a to-many relation keeps the stored order through `FIND_IN_SET()` — which cannot order values that
contain a comma — and a value listed twice yields its record once. The values are bound as query parameters; a
to-many relation to `tl_files`.`uuid` goes through `FilesModel::findMultipleByUuids()`.

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

Templates and listeners work with the row; no entry becomes a Contao model. Where a Contao API requires one, e.g.
`News::getSchemaOrgData()`, load the record by `meta->modelRef` through the finder of the table's model class:
`Model::getClassFromTable($ref->table)::findById($ref->id)`, or the concrete class (`NewsModel::findById($ref->id)`).
The table's own class matters where another bundle registers one — a multilingual model class, for example,
translates the record. `meta->modelRef` is `null` for an entry that names no record of a DC table: check it (and its
table) yourself before you call a finder. The bundled News schema.org listener does it like this:

```php
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Model;
use Contao\News;
use Contao\NewsModel;
use HeimrichHannot\FlareBundle\Event\ReaderSchemaOrgEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 200)]
class NewsReaderSchemaOrgListener
{
    public function __construct(private readonly ContaoFramework $framework) {}

    public function __invoke(ReaderSchemaOrgEvent $event): void
    {
        $ref = $event->entry->meta->modelRef;

        if ($ref?->table !== NewsModel::getTable()) {
            return;
        }

        $class = $this->framework->getAdapter(Model::class)->getClassFromTable($ref->table);
        $model = $this->framework->getAdapter($class)->findById($ref->id);

        if (!$model instanceof NewsModel) {
            return;
        }

        $event->data = $this->framework->getAdapter(News::class)->getSchemaOrgData($model);
    }
}
```

What the finder hands you is Contao's, not Flare's:

- **The model is the database record.** Values a driver or listener mapped into the row, and columns of the row that
  are not the table's (e.g. from a join), are not in it.
- **Contao's model registry answers first.** A record that was already registered earlier in the request is returned
  as it is, without a query — whichever class registered it. The list's query has already confirmed that the record
  is a member of the list, so this is the same model any Contao module would get for it.
- **The finder's lifecycle applies.** A core model is registered when it is loaded and can be saved. A model of
  DC_Multilingual is detached: it is not registered and cannot be saved, and every call queries again.
- **One query per call**, unless the registry holds the record. For the bundled schema.org listeners that is one
  query more on a reader page, where `flare_schema_org()` belongs. Called with an entry and a list elsewhere, it
  costs that query for each entry it is called with.
- **`null` for a deleted record**, unless the registry still holds an instance of it.

## 9. Request Context

A reader page resolves its reader once per request, and the breadcrumb and the reader share the result. The breadcrumb
usually renders first — in the **main request**, before the reader's fragment sub-request exists. So the reader's
entries event, the lazy values attached in it and the `ReaderPageMetaEvent` may run while the breadcrumb renders.

Listeners must not depend on the fragment request: do not expect the reader element's request attributes in
`RequestStack::getCurrentRequest()`. Take what you need from the event (`list`, `intent`, `entries`) or from the main
request.
