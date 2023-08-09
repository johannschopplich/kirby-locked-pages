<?php

use JohannSchopplich\LockedPages;
use Kirby\Cms\App;

return [
    'route:after' => function (\Kirby\Http\Route $route, string $path, string $method, $result, bool $final) {
        if (!$final) {
            return;
        }

        if (!($result instanceof \Kirby\Cms\Page)) {
            return;
        }

        if (!LockedPages::isLocked($result)) {
            return;
        }

        $kirby = App::instance();
        $slug = ($kirby->multilang() ? $kirby->language()->url() . '/' : '') . option('johannschopplich.locked-pages.slug', 'locked');
        $options = [
            'query' => ['redirect' => $result->uri()]
        ];

        go(url($slug, $options));
    },

    'locked-pages.logout' => function () {
        $kirby = App::instance();
        $key = LockedPages::SESSION_KEY;
        $kirby->session()->data()->remove($key);
    }
];
