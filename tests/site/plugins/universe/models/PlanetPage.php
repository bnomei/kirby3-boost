<?php

namespace Universe;

use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;

class PlanetPage extends \Bnomei\BoostPage
{
    public static function create(array $props): Page
    {
        $page = parent::create($props);
        $page = $page->changeStatus('unlisted');
        return $page;
    }

    public function humans(): Pages
    {
        return $this->children()->filterBy('template', 'human');
    }

    private function randomGender(): ?string
    {
        return A::first(A::shuffle([
            'male', 'male', 'male', 'male', 'male', 'male', 'male', 'male', 'male', 
            'female', 'female', 'female', 'female', 'female', 'female', 'female', 'female', 'female', 
            null
        ]));
    }

    public function createHuman(array $content) 
    {
        $props['template'] = 'human';
        $props['content'] = $content;
        return $this->createChild($props);
    }

    public function addSingle()
    {
        $faker = \Faker\Factory::create();
        $gender = $this->randomGender();
        $this->createHuman([
            'gender' => $gender ?? 'other',
            'firstname' => $faker->firstName($gender),
            'lastname' => $faker->lastName(),
            'birthday' => $faker->dateTimeBetween('-100 years', '-18 years')->format('Ymd'),
            'karma' => random_int(-100, 100),
        ]);
    }

    public function addFamily()
    {
        $faker = \Faker\Factory::create();
        $lastname = $faker->lastName();
        $la = random_int(23, 42);
        $gender = $this->randomGender();
        $a = $this->createHuman([
            'gender' => $gender ?? 'other',
            'firstname' => $faker->firstName($gender),
            'lastname' => $lastname,
            'birthday' => $faker->dateTimeBetween('-'.($la+1).' years', '-'.($la).' years')->format('Ymd'),
            'karma' => random_int(-100, 100),
        ]);

        $gender = $this->randomGender();
        $lb = random_int(-5, +5);
        $b = $this->createHuman([
            'gender' => $gender ?? 'other',
            'firstname' => $faker->firstName($gender),
            'lastname' => $lastname,
            'birthday' => $faker->dateTimeBetween('-'.($la+$lb+1).' years', '-'. ($la+$lb) .' years')->format('Ymd'),
            'karma' => random_int(-100, 100),
            //'partner' => $a->boostid()->value(),
        ]);

        $k = A::first(A::shuffle([
            0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,
            1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,
            2,2,2,2,2,2,2,2,2,2,2,2,2,2,
            3,3,3,3,
            4,4,
            5,
        ]));
        $ks = [];
        for($i = 0; $i < $k; $i++) {
            $lk = random_int(0, min($la, $la+$lb) - 18);
            $gender = $this->randomGender();
            $ki = $this->createHuman([
                'gender' => $gender ?? 'other',
                'firstname' => $faker->firstName($gender),
                'lastname' => $lastname,
                'birthday' => $faker->dateTimeBetween('-'.($lk+1).' years', '-'. ($lk) .' years')->format('Ymd'),
                'karma' => random_int(-100, 100),
                'parents' => $a->boostid()->value() . ',' . $b->boostid()->value(),
            ]);
            $ks[] = $ki->boostid()->value();
        }

        $a = $a->update([
            'kids' => implode(',', $ks),
            'partner' => $b->boostid()->value(),
        ]);

        $b = $b->update([
            'kids' => implode(',', $ks),
            'partner' => $a->boostid()->value(),
        ]);
    }
}