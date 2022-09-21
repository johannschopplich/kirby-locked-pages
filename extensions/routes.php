<?php

return [
    [
        'pattern' => option('johannschopplich.locked-pages.slug', 'locked'),
        'method' => 'GET|POST',
        'language' => '*',
        'action' => function () {
            if (get('redirect') === null) {
                return false;
            }

            return new \Kirby\Cms\Page([
                'slug' => option('johannschopplich.locked-pages.slug', 'locked'),
                'template' => option('johannschopplich.locked-pages.template', 'locked-pages-login'),
                'content' => [
                    'title' => option('johannschopplich.locked-pages.title', 'Page locked')
                ]
            ]);
        }
    ]
];
