<?php

require_once __DIR__.'/../vendor/autoload.php';
use Bnomei\Bolt;
use Kirby\Cms\Page;
use Kirby\Toolkit\Str;

test('index', function () {
    $index = site()->index()->toArray();
    expect(count($index) > 0)->toBeTrue();
});
test('construct', function () {
    $bolt = new Bnomei\Bolt();
    expect($bolt)->toBeInstanceOf(Bnomei\Bolt::class);

    expect(option('debug'))->toBeFalse();

    $bolt->flush();
});
test('find', function () {
    $randomPage = randomPage();
    site()->prune();

    $page = bolt($randomPage->id());

    // bolt page is lazy loaded
    $this->assertNotEquals($randomPage, $page);

    // test kirbys lazy loading
    expect($page->id())->toEqual($randomPage->id());
    expect($page->num())->toEqual($randomPage->num());
    expect($page->url())->toEqual($randomPage->url());
    expect($page->title()->value())->toEqual($randomPage->title()->value());
    expect($page->diruri())->toEqual($randomPage->diruri());
    if ($randomPage->parent()) {
        expect(Str::slug($page->parent()->root()))->toEqual(Str::slug($randomPage->parent()->root()));
        expect($page->siblings()->count())->toEqual($randomPage->siblings()->count());
        expect($page->siblings()->first()->id())->toEqual($randomPage->siblings()->first()->id());
    }
});
test('shortcut repeated lookup', function () {
    $randomPage = randomPage();
    site()->prune();

    $page = bolt($randomPage->id());
    $page2 = bolt($randomPage->id());
    expect($page2)->toEqual($page);
});
test('shortcut silblings lookup', function () {
    $randomPage = null;
    $randomSibl = null;
    while ($randomSibl == null) {
        $randomPage = randomPage();
        // has sibling and not under site
        $randomSibl = $randomPage->siblings()->count() && $randomPage->parent() != null ?
            $randomPage->siblings()->shuffle()->first() : null;
    }
    site()->prune();

    $page = bolt($randomPage->id());
    $page2 = bolt($randomSibl->id());
    expect($page2->parent()->id())->toEqual($page->parent()->id());
});
test('page method', function () {
    $randomPage = null;
    $parent = null;
    while (! $parent) {
        $randomPage = randomPage();
        $parent = $randomPage->parent();
    }
    site()->prune();

    $page = $parent->bolt($randomPage->slug());

    expect($page->id())->toEqual($randomPage->id());
});
test('find by diruri', function () {
    $randomPage = randomPage();
    $randomPageDiruri = $randomPage->diruri();
    site()->prune();

    $page = bolt($randomPageDiruri);

    expect($page->diruri())->toEqual($randomPageDiruri);
    expect($page->id())->toEqual($randomPage->id());
});
test('bolt index', function () {
    $count = 0;
    \Bnomei\Bolt::index(function ($page) use (&$count) {
        $count += 1;
    });
    expect($count)->toEqual(site()->index(true)->count());
});
