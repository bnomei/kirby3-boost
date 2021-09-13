<?php

autoloader(__DIR__)->classes();


Kirby::plugin('universe/things', [

	'blueprints' => autoloader(__DIR__)->blueprints(),
	'collections' => autoloader(__DIR__)->collections(),
	'pageModels' => autoloader(__DIR__)->pageModels(),

	'templates' => [
		'factory' 	=> __DIR__ . '/templates/empty.php',
		'galaxy' 	=> __DIR__ . '/templates/empty.php',
		'human' 	=> __DIR__ . '/templates/empty.php',
		'humankind' => __DIR__ . '/templates/empty.php',
		'planet' 	=> __DIR__ . '/templates/empty.php',
		'spaceship' => __DIR__ . '/templates/empty.php',
		'star' 		=> __DIR__ . '/templates/empty.php',
		'system' 	=> __DIR__ . '/templates/empty.php',
	],

	'fieldMethods' => [
		'toCount' => function($field): int
		{
			return count($field->split());
		},
		'toInlineJson' => function($field): string
		{
			return str_replace('\/', '/', json_encode(json_decode($field->value(), true), JSON_PRETTY_PRINT));
		},
	],

	"routes" => [
		[
			'pattern' => 'simuation-tick',
		    'action'  => function () {
		        return [
		        	'tick' => page('milkyway')->simulationTick(),
		        ];
		    }
		]
	],

]);

class_alias("Universe\\HumanPage", "HumanPage");
