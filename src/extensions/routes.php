<?php

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;

return function (App $kirby) {
    $slug = $kirby->option('johannschopplich.locked-pages.slug');

    return [
        [
            'pattern' => $slug,
            'method' => 'GET|POST',
            'language' => '*',
            'action' => function () use ($kirby, $slug) {
                $request = $kirby->request();
                // Query and body values may be arrays (`?redirect[]=…`), so only strings pass.
                $input = fn (string $key): string|null => is_string($value = $request->get($key)) ? $value : null;
                $redirect = $input('redirect');
                $targetPage = $redirect !== null ? $kirby->site()->find($redirect) : null;

                if ($targetPage === null) {
                    return false;
                }

                if (!Guard::isLocked($targetPage)) {
                    return Response::redirect($targetPage->url());
                }

                $error = false;

                if ($request->is('POST')) {
                    // `Guard::isLocked()` returned true, so `find()` has a protected page to return.
                    $protectedPage = Guard::find($targetPage);

                    if ($kirby->csrf($input('csrf')) === false) {
                        $error = $kirby->option('johannschopplich.locked-pages.error.csrf');
                    } elseif (Guard::verify($protectedPage, $input('password'))) {
                        Guard::grant($protectedPage);

                        return Response::redirect($targetPage->url());
                    } else {
                        $error = $kirby->option('johannschopplich.locked-pages.error.password');
                    }
                }

                $loginPage = new Page([
                    'slug' => $slug,
                    'template' => $kirby->option('johannschopplich.locked-pages.template'),
                    'content' => [
                        'title' => $kirby->option('johannschopplich.locked-pages.title')
                    ]
                ]);

                return $loginPage->render(['error' => $error]);
            }
        ]
    ];
};
