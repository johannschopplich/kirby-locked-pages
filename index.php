<?php

load([
    'KirbyExtended\\LockedPages' => 'classes/KirbyExtended/LockedPages.php'
], __DIR__);

\Kirby\Cms\App::plugin('johannschopplich/kirby-locked-pages', [
    'hooks' => [
        'route:after' => [\KirbyExtended\LockedPages::class, 'routeHook'],
        'locked-pages.logout' => [\KirbyExtended\LockedPages::class, 'logoutHook']
    ],
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
