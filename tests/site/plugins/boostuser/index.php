<?php

class AdminUser extends \Kirby\Cms\User
{
    use \Bnomei\ModelHasBoost;

    public function hello(): string
    {
        return 'world';
    }
}

Kirby::plugin('myplugin/user', [
    'userModels' => [
        'admin' => AdminUser::class, // admin is default role
    ],
]);
