<?php

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Page;
use KirbyExtended\LockedPages;

Kirby::plugin('johannschopplich/kirby-locked-pages', [
    'hooks' => [
        'route:after' => function ($route, $path, $method, $result, $final) {
            if (!$final) return;
            if (!is_a($result, Page::class)) return;
            if (!LockedPages::isLocked($result)) return;

            $options = [
                'query' => ['redirect' => $path]
            ];
            $slug = option('kirby-extended.locked-pages.slug', 'locked');

            go(url($slug, $options));
        }
    ],
    'routes' => [
        [
            'pattern' => option('kirby-extended.locked-pages.slug', 'locked'),
            'method' => 'GET|POST',
            'language' => '*',
            'action' => function () {
                if (get('redirect') === null) {
                    return false;
                }

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
