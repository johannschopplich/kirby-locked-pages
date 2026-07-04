<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Cms\Page;

return function (App $kirby) {
    return [
        [
            'pattern' => $kirby->option('johannschopplich.locked-pages.slug', 'locked'),
            'method' => 'GET|POST',
            'language' => '*',
            'action' => function () use ($kirby) {
                if ($kirby->request()->get('redirect') === null) {
                    return false;
                }

                return new Page([
                    'slug' => $kirby->option('johannschopplich.locked-pages.slug', 'locked'),
                    'template' => $kirby->option('johannschopplich.locked-pages.template', 'locked-pages-login'),
                    'content' => [
                        'title' => $kirby->option('johannschopplich.locked-pages.title', 'Page locked')
                    ]
                ]);
            }
        ]
    ];
};
