<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\BoostCache;
use Kirby\Cms\Page;
use PHPUnit\Framework\TestCase;

final class PageHasBoostTest extends TestCase
{
    public function randomPage(): ?Page
    {
        return site()->index()->notTemplate('home')->shuffle()->first();
    }

    public function randomPageWithChildren(): ?Page
    {
        $page = null;
        while (!$page) {
            $rp = $this->randomPage();
            if ($rp->hasChildren()) {
                $page = $rp;
            }
        }
        return $page;
    }

    public function testCreateWillAddBoostId()
    {
        $newPage = site()->createChild([
            'slug' => 'willthishaveboostid',
            'title' => 'Will this Have BoostId',
            'content' => [
                // 'boostid' => null,
            ],
        ])->publish();
        site()->purge();
        $newPage = page('willthishaveboostid');

        $this->assertTrue($newPage->boostid()->isNotEmpty());

        $newPage->delete(true);
    }

    public function testWillForceOnDupblicate()
    {
        $randomPage = $this->randomPage();
        $bid1 = $randomPage->boostid()->value();

        $newPage = $randomPage->duplicate('newboostid')->publish();
        $newPageId = $newPage->id();
        site()->purge();
        $newPage = page($newPageId);

        $this->assertTrue($newPage->boostid()->isNotEmpty());
        $this->assertNotEquals($bid1, $newPage->boostid()->value());

        $newPage->delete(true);
    }

    public function testDeleteCacheOnDelete()
    {
        $newPage = site()->createChild([
            'slug' => 'willthisbedeleted',
            'title' => 'Will this be Deleted',
            'content' => [
                // 'boostid' => null,
            ],
        ])->publish();
        site()->purge();
        $newPage = page('willthisbedeleted');

        $this->assertTrue($newPage->boostid()->isNotEmpty());

        $this->assertNotNull($newPage->readContentCache());
        $key = $newPage->contentBoostedKey();


        $newPage->delete(true);

        //$this->assertNull($newPage->readContentCache()); // this would create again
        $cache = BoostCache::singleton();
        $this->assertNull($cache->get($key . '/content'));
        $this->assertNull($cache->get($key . '/modified'));
    }

    public function testExpiredByModified()
    {
        $randomPage = $this->randomPage();
        $key = $randomPage->contentBoostedKey();
        $cache = BoostCache::singleton();

        // fake outdated modified value
        $this->assertTrue($cache->set($key . '/modified', $randomPage->modified() - 1));
        $this->assertTrue($randomPage->isContentCacheExpiredByModified());

        // fake in future
        $this->assertTrue($cache->set($key . '/modified', $randomPage->modified() + 1));
        $this->assertFalse($randomPage->isContentCacheExpiredByModified());

        // fake current (to reset for next tests)
        $this->assertTrue($cache->set($key . '/modified', $randomPage->modified() + 0));
        $this->assertFalse($randomPage->isContentCacheExpiredByModified());
    }
}
