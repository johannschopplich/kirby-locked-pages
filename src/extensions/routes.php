<?php

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Toolkit\I18n;

return function (App $kirby) {
    $slug = $kirby->option('johannschopplich.locked-pages.slug');
    $text = fn (string $key): string => $kirby->option('johannschopplich.locked-pages.' . $key) ?? I18n::translate('johannschopplich.locked-pages.' . $key);

    return [
        [
            'pattern' => $slug,
            'method' => 'GET|POST',
            'language' => '*',
            'action' => function () use ($kirby, $slug, $text) {
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
                        $error = $text('error.csrf');
                    } elseif (Guard::verify($protectedPage, $input('password'))) {
                        Guard::grant($protectedPage);

                        return Response::redirect($targetPage->url());
                    } else {
                        $error = $text('error.password');
                    }
                }

                $loginPage = new Page([
                    'slug' => $slug,
                    'template' => $kirby->option('johannschopplich.locked-pages.template'),
                    'content' => [
                        'title' => $text('title')
                    ]
                ]);

                return $loginPage->render(['error' => $error]);
            }
        ]
    ];
};
