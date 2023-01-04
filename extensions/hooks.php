<?php

use JohannSchopplich\LockedPages;

return [
    'route:after' => function ($route, $path, $method, $result, $final) {
        if (!$final) {
            return;
        }
        if (!is_a($result, \Kirby\Cms\Page::class)) {
            return;
        }
        if (!LockedPages::isLocked($result)) {
            return;
        }

        $slug = option('johannschopplich.locked-pages.slug', 'locked');
        $options = [
            'query' => ['redirect' => $result->id()]
        ];

        go(url($slug, $options));
    },

    'locked-pages.logout' => function () {
        $key = LockedPages::SESSION_KEY;
        kirby()->session()->data()->remove($key);
    }
];
