<?php

Kirby::plugin('universe/things', [

	'collections' => [
		'humans' => require __DIR__ . '/collections/humans.php',
		'planets' => require __DIR__ . '/collections/planets.php',
		'spaceships' => require __DIR__ . '/collections/spaceships.php',
		'suns' => require __DIR__ . '/collections/suns.php',
	],

	'fieldMethods' => [
		'toInlineJson' => function($field): string
		{
			return json_encode(json_decode($field->value(), true), JSON_PRETTY_PRINT);
		},
	],

]);