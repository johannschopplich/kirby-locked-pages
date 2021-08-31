<?php

return [
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
];
