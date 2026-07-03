<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;

return [
    'route:after' => function (\Kirby\Http\Route $route, string $path, string $method, $result, bool $final) {
        if (!$final) {
            return;
        }

        if (!($result instanceof \Kirby\Cms\Page)) {
            return;
        }

        if (!Guard::isLocked($result)) {
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
        $kirby->session()->data()->remove(Guard::SESSION_KEY);
    }
];
