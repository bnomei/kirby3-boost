<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kirby\Cms\Page;
use PHPUnit\Framework\TestCase;

final class BoostTest extends TestCase
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

    public function testBoostSiteIndex()
    {
        $index = \Bnomei\BoostIndex::singleton();
        $index->index(true);
        $index->flush();
        $count = site()->boost();
        $this->assertCount(kirby()->site()->index(true)->count(), $index->toArray());
        $this->assertCount($count, $index->toArray());
    }

    public function testHelperBolt()
    {
        $randomPage = $this->randomPage();
        $randomPage->boost();
        $this->assertEquals($randomPage->id(), bolt($randomPage->diruri())->id());
    }

    public function testHelperModified()
    {
        $randomPage = $this->randomPage();
        $randomPage->boost();
        $this->assertEquals($randomPage->modified(), modified($randomPage->id()));
    }

    public function testHelperBoost()
    {
        $randomPage = $this->randomPage();
        $randomPage->boost();
        $this->assertEquals($randomPage->id(), boost($randomPage->uuid()->id())->id());
    }

    public function testPageMethodBolt()
    {
        $randomPage = $this->randomPageWithChildren();
        $lastChild = $randomPage->children()->last();
        $this->assertEquals(
            $lastChild->id(),
            $randomPage->bolt($lastChild->slug())->id()
        );
    }

    public function testPageMethodBoost()
    {
        $randomPage = $this->randomPage();
        $this->assertTrue($randomPage->boost());
    }

    public function testPageMethodIsBoosted()
    {
        $randomPage = $this->randomPage();
        $randomPage->deleteContentCache();

        $this->assertFalse($randomPage->isBoosted());
        $this->assertTrue($randomPage->boost());
        $this->assertTrue($randomPage->isBoosted());
    }

    public function testPagesMethodBoost()
    {
        $randomPage = $this->randomPageWithChildren();

        \Bnomei\BoostIndex::singleton()->index(true);
        $before = \Bnomei\BoostIndex::singleton()->count();
        $this->assertIsFloat($randomPage->children()->boostIndexRemove());
        $after = \Bnomei\BoostIndex::singleton()->count();
        $this->assertEquals($after, $before - $randomPage->children()->count());

        $before = \Bnomei\BoostIndex::singleton()->count();
        $this->assertIsFloat($randomPage->children()->boostIndexAdd());
        $after = \Bnomei\BoostIndex::singleton()->count();
        $this->assertEquals($after, $before + $randomPage->children()->count());

        $this->assertIsFloat($randomPage->children()->boost());
        $this->assertIsInt(site()->boost());
    }

    public function testPagesMethodBoostmark()
    {
        $randomPage = $this->randomPageWithChildren();

        \Bnomei\BoostIndex::singleton()->index(true);
        \Bnomei\BoostIndex::singleton()->flush();
        $this->assertIsFloat($randomPage->children()->unboost());
        $before = \Bnomei\BoostIndex::singleton()->count();
        $boostmark = $randomPage->children()->boostmark();
        $this->assertIsArray($boostmark);
        $this->assertEquals($randomPage->children()->count(), $boostmark['count']);
        $after = \Bnomei\BoostIndex::singleton()->count();
        $this->assertEquals($after, $before + $randomPage->children()->count());
        $boostmark2 = $randomPage->children()->boostmark();
        $this->assertEquals($boostmark2['checksum'], $boostmark['checksum']);

        // trigger file write
        kirby()->impersonate('kirby');
        $randomPage->update([
            'title' => $randomPage->title()->value(),
        ]);
        $boostmark3 = site()->boostmark();
        $this->assertIsArray($boostmark3);
        $this->assertNotEquals($boostmark3['checksum'], $boostmark['checksum']);
    }

    public function testFieldMethodsBoost()
    {
        $randomPage = $this->randomPage();
        // works only in 2nd test run when kirby process
        // can read the updated content files from WithPagesTest
        if ($randomPage->related()->isNotEmpty()) {
            $many = $randomPage->related()->toPagesBoosted();
            $this->assertNotNull($many);

            kirby()->impersonate('kirby');
            $randomPage = $randomPage->update([
                'related' => $randomPage->related()->split()[0],
            ]);
            $one = $randomPage->related()->toPageBoosted();
            $this->assertNotNull($one);
        } else {
            $this->markTestSkipped();
        }
    }
}
