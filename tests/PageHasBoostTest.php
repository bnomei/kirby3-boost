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

    public function testDeleteCacheOnDelete()
    {
        kirby()->impersonate('kirby');
        page('willthisbedeleted')?->delete();

        $newPage = site()->createChild([
            'slug' => 'willthisbedeleted',
            'title' => 'Will this be Deleted',
            'content' => [
                // 'boostid' => null,
            ],
        ])->publish();
        site()->purge();
        $newPage = page('willthisbedeleted');

        $this->assertNotNull($newPage->readContentCache());
        $key = $newPage->contentBoostedKey();


        $newPage->delete(true);

        //$this->assertNull($newPage->readContentCache()); // this would create again
        $cache = BoostCache::singleton();
        $this->assertNull($cache->get($key . '-content'));
        $this->assertNull($cache->get($key . '-modified'));
    }

    public function testExpiredByModified()
    {
        $randomPage = $this->randomPage();
        $key = $randomPage->contentBoostedKey();
        $cache = BoostCache::singleton();

        // fake outdated modified value
        $this->assertTrue($cache->set($key . '-modified', $randomPage->modified() - 1));
        $this->assertTrue($randomPage->isContentCacheExpiredByModified());

        // fake in future
        $this->assertTrue($cache->set($key . '-modified', $randomPage->modified() + 1));
        $this->assertFalse($randomPage->isContentCacheExpiredByModified());

        // fake current (to reset for next tests)
        $this->assertTrue($cache->set($key . '-modified', $randomPage->modified() + 0));
        $this->assertFalse($randomPage->isContentCacheExpiredByModified());
    }
}
