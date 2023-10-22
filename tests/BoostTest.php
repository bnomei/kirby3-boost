<?php

require_once __DIR__.'/../vendor/autoload.php';

use Kirby\Cms\Page;

function randomPageWithChildren(): ?Page
{
    $page = null;
    while (! $page) {
        $rp = randomPage();
        if ($rp->hasChildren()) {
            $page = $rp;
        }
    }

    return $page;
}
test('helper bolt', function () {
    $randomPage = randomPage();
    $randomPage->boost();
    expect(bolt($randomPage->diruri())->id())->toEqual($randomPage->id());
});
test('helper modified', function () {
    $randomPage = randomPage();
    $randomPage->boost();
    expect(modified($randomPage->id()))->toEqual($randomPage->modified());
});
test('page method bolt', function () {
    $randomPage = randomPageWithChildren();
    $lastChild = $randomPage->children()->last();
    expect($randomPage->bolt($lastChild->slug())->id())->toEqual($lastChild->id());
});
test('page method boost', function () {
    $randomPage = randomPage();
    expect($randomPage->boost())->toBeTrue();
});
test('page method is boosted', function () {
    $randomPage = randomPage();
    $randomPage->deleteContentCache();

    expect($randomPage->isBoosted())->toBeFalse();
    expect($randomPage->boost())->toBeTrue();
    expect($randomPage->isBoosted())->toBeTrue();
});
test('pages method boost', function () {
    $randomPage = randomPageWithChildren();

    \Bnomei\BoostIndex::singleton()->index(true);
    $before = \Bnomei\BoostIndex::singleton()->count();
    expect($randomPage->children()->boostIndexRemove())->toBeFloat();
    $after = \Bnomei\BoostIndex::singleton()->count();
    expect($before - $randomPage->children()->count())->toEqual($after);

    $before = \Bnomei\BoostIndex::singleton()->count();
    expect($randomPage->children()->boostIndexAdd())->toBeFloat();
    $after = \Bnomei\BoostIndex::singleton()->count();
    expect($before + $randomPage->children()->count())->toEqual($after);

    expect($randomPage->children()->boost())->toBeFloat();
    expect(site()->boost())->toBeInt();
});
test('pages method boostmark', function () {
    $randomPage = randomPageWithChildren();

    \Bnomei\BoostIndex::singleton()->index(true);
    \Bnomei\BoostIndex::singleton()->flush();
    expect($randomPage->children()->unboost())->toBeFloat();
    $before = \Bnomei\BoostIndex::singleton()->count();
    $boostmark = $randomPage->children()->boostmark();
    expect($boostmark)->toBeArray();
    expect($boostmark['count'])->toEqual($randomPage->children()->count());
    $after = \Bnomei\BoostIndex::singleton()->count();
    expect($before + $randomPage->children()->count())->toEqual($after);
    $boostmark2 = $randomPage->children()->boostmark();
    expect($boostmark['checksum'])->toEqual($boostmark2['checksum']);

    // trigger file write
    kirby()->impersonate('kirby');
    $randomPage->update([
        'title' => $randomPage->title()->value(),
    ]);
    $boostmark3 = site()->boostmark();
    expect($boostmark3)->toBeArray();
    $this->assertNotEquals($boostmark3['checksum'], $boostmark['checksum']);
});
test('field methods boost', function () {
    $randomPage = randomPage();

    // works only in 2nd test run when kirby process
    // can read the updated content files from WithPagesTest
    if ($randomPage->related()->isNotEmpty()) {
        $many = $randomPage->related()->fromBoostIDs();
        expect($many)->not->toBeNull();

        kirby()->impersonate('kirby');
        $randomPage = $randomPage->update([
            'related' => $randomPage->related()->split()[0],
        ]);
        $one = $randomPage->related()->fromBoostID();
        expect($one)->not->toBeNull();
    } else {
        $this->markTestSkipped();
    }
});
test('boost site index', function () {
    $index = \Bnomei\BoostIndex::singleton();
    $index->index(true);
    $index->flush();
    $count = site()->boost();
    expect($index->toArray())->toHaveCount(count(kirby()->collection('boostidkvs')));
    expect($index->toArray())->toHaveCount($count);
});
test('non translatable', function () {
    $randomPage = randomPage();

    expect($randomPage->text()->value())->toEqual('translated EN');
    expect($randomPage->nt_text()->value())->toEqual('not translated');

    expect($randomPage->content('en')->text()->value())->toEqual('translated EN');
    expect($randomPage->content('en')->nt_text()->value())->toEqual('not translated');

    expect($randomPage->content('de')->text()->value())->toEqual('translated DE');
    expect($randomPage->content('de')->nt_text()->value())->toEqual('not translated');

    $this->markTestSkipped('using content() does not reflect what routing does. tested manually in browser.');
});
test('boost can load file', function () {
    $fileUuid = 'file://hp4IB3c6UxKODRyK';
    $time = microtime(true);
    $c = 10000;
    while ($c > 0) {
        $file = boost($fileUuid);
        $c--;
    }
    echo 'boost(): '.(microtime(true) - $time).PHP_EOL;
    expect($fileUuid)->toEqual($file->uuid()->toString());
});
test('kirby can load file', function () {
    $fileUuid = 'file://hp4IB3c6UxKODRyK';
    $time = microtime(true);
    $c = 10000;
    while ($c > 0) {
        $file = site()->file($fileUuid);
        $c--;
    }
    echo 'site()->file(): '.(microtime(true) - $time).PHP_EOL;
    expect($fileUuid)->toEqual($file->uuid()->toString());
});
