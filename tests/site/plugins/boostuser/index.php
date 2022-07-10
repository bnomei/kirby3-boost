<?php

class AdminUser extends \Bnomei\BoostUser
{
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
