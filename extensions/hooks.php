<?php

return [
    'route:after' => function ($route, $path, $method, $result, $final) {
        if (!$final) return;
        if (!is_a($result, \Kirby\Cms\Page::class)) return;
        if (!\KirbyExtended\LockedPages::isLocked($result)) return;

        $slug = option('kirby-extended.locked-pages.slug', 'locked');
        $options = [
            'query' => ['redirect' => $result->id()]
        ];

        go(url($slug, $options));
    },

    'locked-pages.logout' => function () {
        $key = \KirbyExtended\LockedPages::SESSION_KEY;
        kirby()->session()->data()->remove($key);
    }
];
