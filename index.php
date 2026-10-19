<?php

use Kirby\Cms\App;
use Kirby\Filesystem\F;

F::loadClasses([
    'JohannSchopplich\\LockedPages\\Guard' => 'src/classes/Guard.php'
], __DIR__);

App::plugin('johannschopplich/locked-pages', [
    'options' => [
        'slug' => 'locked',
        'template' => 'locked-pages-login',
        'title' => 'Page locked',
        'longSession' => true,
        'error' => [
            'password' => 'The password is incorrect',
            'csrf' => 'The CSRF token is invalid'
        ]
    ],
    'hooks' => require __DIR__ . '/src/extensions/hooks.php',
    'routes' => require __DIR__ . '/src/extensions/routes.php',
    'blueprints' => [
        'fields/locked-pages' => __DIR__ . '/src/blueprints/fields/locked-pages.yml'
    ],
    'templates' => [
        'locked-pages-login' => __DIR__ . '/src/templates/locked-pages-login.php'
    ]
]);
