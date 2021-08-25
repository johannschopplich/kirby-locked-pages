<?php

load([
    'KirbyExtended\\LockedPages' => 'classes/KirbyExtended/LockedPages.php'
], __DIR__);

\Kirby\Cms\App::plugin('johannschopplich/kirby-locked-pages', [
    'hooks' => [
        'route:after' => function ($route, $path, $method, $result, $final) {
            if (!$final) return;
            if (!is_a($result, \Kirby\Cms\Page::class)) return;
            if (!\KirbyExtended\LockedPages::isLocked($result)) return;

            $slug = option('kirby-extended.locked-pages.slug', 'locked');
            $options = [
                'query' => ['redirect' => $result->id()]
            ];

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

                return new \Kirby\Cms\Page([
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
