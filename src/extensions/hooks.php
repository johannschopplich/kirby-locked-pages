<?php

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Http\Route;
use Kirby\Http\Url;

return [
    'route:after' => function (Route $route, string $path, string $method, $result, bool $final) {
        if (!$final) {
            return;
        }

        // Representations (`.json`, `.xml`, …) resolve to a `Response`, not
        // a `Page`, so recover the owning page from the path to lock them too.
        $page = $result instanceof Page ? $result : Guard::resolveFromRoutePath($path);

        if (!Guard::isLocked($page)) {
            return;
        }

        $kirby = App::instance();
        $loginUrl = $kirby->site()->url() . '/' . $kirby->option('johannschopplich.locked-pages.slug');

        return Response::redirect(Url::to($loginUrl, [
            'query' => ['redirect' => $page->uri()]
        ]));
    },

    'locked-pages.logout' => function () {
        Guard::session()->data()->remove(Guard::SESSION_KEY);
    }
];
