<?php

use Kirby\Cms\App;

load([
    'JohannSchopplich\\LockedPages' => 'LockedPages.php'
], __DIR__);

App::plugin('johannschopplich/locked-pages', [
    'hooks' => require __DIR__ . '/extensions/hooks.php',
    'routes' => require __DIR__ . '/extensions/routes.php',
    'blueprints' => [
        'fields/locked-pages' => __DIR__ . '/blueprints/fields/locked-pages.yml'
    ],
    'controllers' => [
        'locked-pages-login' => require __DIR__ . '/controllers/locked-pages-login.php'
    ],
    'templates' => [
        'locked-pages-login' => __DIR__ . '/templates/locked-pages-login.php'
    ]
]);
