<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Page;
use KirbyExtended\LockedPages;

Kirby::plugin('johannschopplich/kirby-locked-pages', [
    'hooks' => [
        'route:after' => function ($route, $path, $method, $result, $final) {
            if (!$final) return;
            if (!empty($result)) return;
            if (!LockedPages::isLocked($result)) return;

            $slug = option('kirby-extended.locked-pages.slug', 'locked');
            $options = [
                'query' => ['redirect' => $slug]
            ];
            go(url($path, $options));
        }
    ],
    'routes' => [
        [
            'pattern' => option('kirby-extended.locked-pages.slug', 'locked'),
            'method' => 'GET|POST',
            'action' => function () {
                return new Page([
                    'slug' => option('kirby-extended.locked-pages.slug', 'locked'),
                    'template' => option('kirby-extended.locked-pages.template', 'locked-pages-login'),
                    'content' => [
                        'title' => option('kirby-extended.locked-pages.title', 'Page locked')
                    ]
                ]);
            }
        ]
    ],
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
