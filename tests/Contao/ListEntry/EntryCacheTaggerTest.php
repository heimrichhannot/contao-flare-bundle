<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\ListEntry;

use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use HeimrichHannot\FlareBundle\Contao\ListEntry\EntryCacheTagger;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use PHPUnit\Framework\TestCase;

final class EntryCacheTaggerTest extends TestCase
{
    public function testTagsEntriesWithAModelRef(): void
    {
        $responseTagger = new SymfonyResponseTagger();
        $entries = EntryCollection::forTable('tl_news', [['id' => 5], ['id' => 6]]);

        (new EntryCacheTagger($responseTagger))->tag(...$entries, ...new EntryCollection([['id' => 7]]));

        self::assertSame('contao.db.tl_news.5,contao.db.tl_news.6', $responseTagger->getTagsHeaderValue());
    }

    public function testEntriesWithoutAModelRefAddNoTags(): void
    {
        $responseTagger = new SymfonyResponseTagger();

        (new EntryCacheTagger($responseTagger))->tag(...new EntryCollection([['id' => 7]]));

        self::assertFalse($responseTagger->hasTags());
    }

    public function testWithoutAResponseTaggerNothingHappens(): void
    {
        (new EntryCacheTagger())->tag(...EntryCollection::forTable('tl_news', [['id' => 5]]));

        $this->addToAssertionCount(1);
    }
}
