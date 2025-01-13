<?php

require_once __DIR__.'/../vendor/autoload.php';

use Bnomei\BoostIndex;
use Kirby\Toolkit\A;

test('boost flush', function () {
    $index = BoostIndex::singleton();
    $index->flush();
    $index->write();
    expect($index->count())->toEqual(0);
});
test('boost modified', function () {
    $randomPage = randomPage();
    site()->prune();

    // make sure it has an entry
    $triggerContentReadLeadingToCacheWrite = $randomPage->title();

    expect(modified($randomPage->id()))->toEqual($randomPage->modified());

    // get one thats NOT in index
    expect(modified('home'))->toEqual(site()->homePage()->modified());

    expect(modified('does-not-exist'))->toEqual(null);
});
test('boost find by id', function () {
    expect(option('debug'))->toBeFalse();

    $index = BoostIndex::singleton();
    $index->index(true);

    $randomPage = randomPage();

    // site()->prune();
    expect(boost($randomPage->uuid()->id())->id())->toEqual($randomPage->id());
});
test('boost find', function () {
    $index = BoostIndex::singleton();
    $index->index(true);

    $randomPage = randomPage();

    // site()->prune();
    // if (!$randomPage->uuid()->id()) var_dump($randomPage);
    expect(boost($randomPage->uuid())->id())->toEqual($randomPage->id());
});
test('boost index', function () {
    $index = BoostIndex::singleton();
    $index->flush();

    // var_dump($index->toArray());
    expect($index->toArray())->toHaveCount(0);
    $randomPage = randomPage();

    // site()->prune();
    expect($randomPage->uuid()->id())->not->toBeEmpty();
    expect($index->add($randomPage))->toBeTrue();

    // $index->write();
    expect($index->toArray())->toHaveCount(1);
});
test('to page boosted', function () {
    $index = BoostIndex::singleton();
    $index->index(true);

    $randomPage = randomPage();

    // site()->prune();
    expect($randomPage->someUuidRelationField()->toPageBoosted()->id())->toEqual($randomPage->id());
});
test('write on destruct', function () {
    $index = BoostIndex::singleton();
    expect($index->flush())->toBeTrue();
    expect($index->write())->toBeTrue();

    $items = $index->index(true);

    expect($items > 0)->toBeTrue();
    unset($index);

    // trigger write
    $index = BoostIndex::singleton();

    // create and load
    expect($index->count())->toEqual($items);
});
test('crawl for missing', function () {
    $index = BoostIndex::singleton();
    $randomPage = randomPage();
    $boostid = $randomPage->uuid()->id();
    $randomPage->boostIndexAdd();

    expect($randomPage->boostIndexRemove())->toBeTrue();
    expect(A::get($index->toArray(), $boostid))->toBeNull();
    expect($index->find($boostid)->id())->toEqual($randomPage->id());
    expect(A::get($index->toArray(), $boostid))->not->toBeNull();
});
