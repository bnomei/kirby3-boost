<h1>
<?php
    // access any page field to trigger content read
    echo $page->title();
?></h1>

<h3>debug</h3>
<?php var_dump(option('debug')); ?>

<h3>modified</h3>
<?php var_dump($page->modified('c')); ?>

<h3>Memcached</h3>
<?php
$pmk = $page->contentBoostedKey();
$m = \Bnomei\BoostCache::singleton();
if (!$m->get('a')) {
    var_dump($m->set('a', 1234567890));
}
var_dump($m->get('a'));
?>

<h3>bolt</h3>
<?php
var_dump($m->get($pmk . '/bolt'));
var_dump(count(Bnomei\Bolt::toArray()));
bolt($page->id());
var_dump(count(Bnomei\Bolt::toArray()));
?>

<h3>contentBoostedKey</h3>
<?php
    var_dump($pmk);
?>

<h3>isBoosted</h3>
<?php
    var_dump($m->get($pmk . '/modified'));
var_dump($m->get($pmk . '/content'));
var_dump($page->isBoosted());
?>

<h3>boostid</h3>
<?php var_dump($page->uuid()); ?>

<h3>boostindex</h3>
<?php var_dump(Bnomei\BoostIndex::singleton()->toArray()); ?>

<h3>relations</h3>
<?php
// TODO: resolve a lot of relations and track time
$before = microtime(true);
$count = 0;
$pages = $page->related()->toPages();
if ($pages) {
    foreach ($pages as $rel) {
        var_dump($rel->title()->value());
        $count++;
        $pages2 = $rel->related()->toPages();
        foreach ($pages2 as $rel2) {
            var_dump($rel2->title()->value());
            $count++;
            $pages3 = $rel2->related()->toPages();
            foreach ($pages3 as $rel3) {
                var_dump($rel3->title()->value());
                $count++;
            }
        }
    }
}
?>
<h3><?= $count ?> relations in ms</h3>
<?php var_dump(round((microtime(true) - $before) * 1000)); ?>
