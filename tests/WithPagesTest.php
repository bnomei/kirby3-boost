<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kirby\Cms\Page;
use Kirby\Toolkit\Str;
use PHPUnit\Framework\TestCase;

class WithPagesTest extends TestCase
{
    /**
     * @var int
     */
    private $depth;

    /**
     * Only test here will create all pages and set relations
     *
     * @group SetupPagesInSeperatePHPUnitRun
     */
    public function testSetUpPages(): void
    {
        $this->depth = 2;

        if (site()->pages()->children()->notTemplate('home')->count() === 0) {
            for ($i = 0; $i < $this->depth; $i++) {
                $this->createPage(site(), $i, $this->depth);
            }
        }
        $this->assertTrue(site()->pages()->children()->notTemplate('home')->count() > 0);
    }

    public function createPage($parent, int $idx, int $depth = 3): Page
    {
        $id = 'Test ' . abs(crc32(microtime() . $idx . $depth));
        /* @var $page Page */
        kirby()->impersonate('kirby');

        $page = $parent->createChild([
            'slug' => Str::slug($id),
            'template' => 'default',
            'content' => [
                'title' => $id
            ],
        ]);

        $page = $page->changeStatus($idx + $depth % 2 > 0 ? 'listed' : 'unlisted');

        if ($depth > 0) {
            $depth--;
            for ($i = 0; $i < $this->depth; $i++) {
                $this->createPage($page, $i, $depth);
            }
        }

        return $page;
    }

    public function tearDownPages(): void
    {
        kirby()->impersonate('kirby');
        /* @var $page Page */
        foreach (site()->index()->notTemplate('home') as $page) {
            $page->delete(true);
        }
    }

    public function testRelated()
    {
        $boostids = [];

        foreach(site()->index()->filterBy('template', 'default') as $page) {
            $boostids[$page->boostid()->value()] = $page->diruri();
        };

        $this->assertTrue(count($boostids) > 0);
        $this->assertTrue(site()->index()->count() > 1); // not just home

        kirby()->impersonate('kirby');
        foreach (site()->index()->filterBy('template', 'default') as $page) {
            if ($page->related()->isNotEmpty()) {
                continue;
            }

            $page->update([
                'related' => count($boostids) >= 2 ? implode(',', array_rand($boostids, rand(2, min(count($boostids), 10)))) : ''
            ]);
        }
    }
}
