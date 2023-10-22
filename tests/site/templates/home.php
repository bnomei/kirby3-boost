<?php

var_dump(array_keys(site()->index()->toArray()));
var_dump($page->files()->first()->description());

$user = kirby()->users()->first();
var_dump($user->hello());
var_dump($user->description());
