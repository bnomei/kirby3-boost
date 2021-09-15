<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/WithPages.php';

use Bnomei\BoostIndex;

final class BoostIndexTest extends WithPages
{
    public function testBoostModified()
    {
        $randomPage = $this->randomPage();
        site()->prune();

        // make sure it has an entry
        $triggerContentReadLeadingToCacheWrite = $randomPage->title();

        $this->assertEquals($randomPage->modified(), modified($randomPage->id()));
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
}
