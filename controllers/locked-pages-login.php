<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Http\Response;

return function (App $kirby) {
    $targetUri = $kirby->request()->get('redirect');
    $targetPage = $kirby->site()->find($targetUri);

    if ($targetPage === null) {
        return [
            'error' => false
        ];
    }

    if (!Guard::isLocked($targetPage)) {
        Response::go($targetPage->url());
    }

    if (!$kirby->request()->is('POST')) {
        return [
            'error' => false
        ];
    }

    if ($kirby->csrf($kirby->request()->get('csrf')) === false) {
        return [
            'error' => $kirby->option('johannschopplich.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    // `Guard::isLocked()` already proved a protected ancestor exists,
    // so `find()` cannot return null here
    $protectedPage = Guard::find($targetPage);

    if (!Guard::verify($protectedPage, $kirby->request()->get('password'))) {
        return [
            'error' => $kirby->option('johannschopplich.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    Guard::grant($protectedPage);

    Response::go($targetPage->url());
};
