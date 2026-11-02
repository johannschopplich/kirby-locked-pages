<?php

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Http\Route;
use Kirby\Http\Url;

return [
    'route:after' => function (Route $route, string $path, string $method, $result, bool $final) {
        // Kirby answers a CORS preflight itself, without content.
        if (!$final || ($method === 'OPTIONS' && $result instanceof Response)) {
            return;
        }

        $page = $result instanceof Page ? $result : Guard::resolveFromRoute($path, $result);

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
