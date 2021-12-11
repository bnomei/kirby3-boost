<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kirby\Cms\Page;
use Bnomei\BoostIndex;
use Kirby\Toolkit\A;
use PHPUnit\Framework\TestCase;

final class BoostIndexTest extends TestCase
{
    public function randomPage(): ?Page
    {
        return site()->index()->notTemplate('home')->shuffle()->first();
    }

    public function testBoostFlush()
    {
        $index = BoostIndex::singleton();
        $index->flush();
        $index->write();
        $this->assertEquals(0, $index->count());
    }

    public function testBoostModified()
    {
        $randomPage = $this->randomPage();
        site()->prune();

        // make sure it has an entry
        $triggerContentReadLeadingToCacheWrite = $randomPage->title();

        $this->assertEquals($randomPage->modified(), modified($randomPage->id()));

        // get one thats NOT in index
        $this->assertEquals(site()->homePage()->modified(), modified('home'));

        $this->assertEquals(null, modified('does-not-exist'));
    }

    public function testBoostFindById()
    {
        $this->assertFalse(option('debug'));

        $index = BoostIndex::singleton();
        $index->index(true);

        $randomPage = $this->randomPage();
        //site()->prune();

        $this->assertEquals(
            $randomPage->id(),
            boost($randomPage->id())->id()
        );
    }

    public function testBoostFindByBoostId()
    {
        $index = BoostIndex::singleton();
        $index->index(true);

        $randomPage = $this->randomPage();
        //site()->prune();
        //if (!$randomPage->boostIDField()->value()) var_dump($randomPage);


        $this->assertEquals(
            $randomPage->id(),
            boost($randomPage->boostIDField()->value())->id()
        );
    }

    public function testBoostIndex()
    {
        $index = BoostIndex::singleton();
        $index->flush();
        //var_dump($index->toArray());
        $this->assertCount(0, $index->toArray());
        $randomPage = $this->randomPage();
        //site()->prune();

        $this->assertTrue($randomPage->boostIDField()->isNotEmpty());
        $this->assertTrue($index->add($randomPage));
        //$index->write();
        $this->assertCount(1, $index->toArray());
    }

    public function testFromBoostID()
    {
        $index = BoostIndex::singleton();
        $index->index(true);

        $randomPage = $this->randomPage();
        //site()->prune();

        $this->assertEquals(
            $randomPage->id(),
            $randomPage->boostid()->fromBoostID()->id()
        );
    }

    public function testWriteOnDestruct()
    {
        $index = BoostIndex::singleton();
        $this->assertTrue($index->flush());
        $this->assertTrue($index->write());

        $items = $index->index(true);

        $this->assertTrue($items > 0);
        unset($index); // trigger write

        $index = BoostIndex::singleton();
        // create and load
        $this->assertEquals($items, $index->count());
    }

    public function testCrawlForMissing()
    {
        $index = BoostIndex::singleton();
        $randomPage = $this->randomPage();
        $boostid = $randomPage->boostIDField()->value();
        $randomPage->boostIndexAdd();

        $this->assertTrue($randomPage->boostIndexRemove());
        $this->assertNull(A::get($index->toArray(), $boostid));
        $this->assertEquals(
            $randomPage->id(),
            $index->findByBoostId($boostid)->id() // will crawl
        );
        $this->assertNotNull(A::get($index->toArray(), $boostid));
    }
}
