<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;

return [
    'route:after' => function (\Kirby\Http\Route $route, string $path, string $method, $result, bool $final) {
        if (!$final) {
            return;
        }

        // Representations (`.json`, `.xml`, …) resolve to a Response, not a
        // Page, so recover the owning page from the path to lock them too
        $page = $result instanceof \Kirby\Cms\Page ? $result : Guard::resolveFromRoutePath($path);

        if (!Guard::isLocked($page)) {
            return;
        }

        $kirby = App::instance();
        $slug = ($kirby->multilang() ? $kirby->language()->url() . '/' : '') . option('johannschopplich.locked-pages.slug', 'locked');
        $options = [
            'query' => ['redirect' => $page->uri()]
        ];

        go(url($slug, $options));
    },

    'locked-pages.logout' => function () {
        Guard::session()->data()->remove(Guard::SESSION_KEY);
    }
];
