<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Http\Response;

return function (App $kirby) {
    $targetUri = $kirby->request()->get('redirect');
    $targetPage = $kirby->site()->find($targetUri);

    // Ensure target page exists
    if ($targetPage === null) {
        return [
            'error' => false
        ];
    }

    // If page is not locked or user has access already, just go to the page
    if (!Guard::isLocked($targetPage)) {
        Response::go($targetPage->url());
    }

    // Ensure it's a POST request
    if (!$kirby->request()->is('POST')) {
        return [
            'error' => false
        ];
    }

    // Verify the token of the form
    if ($kirby->csrf($kirby->request()->get('csrf')) === false) {
        return [
            'error' => $kirby->option('johannschopplich.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    $protectedPage = Guard::find($targetPage);

    // Verify entered password (constant-time; an empty stored password fails closed)
    if (!Guard::verify($protectedPage, $kirby->request()->get('password'))) {
        return [
            'error' => $kirby->option('johannschopplich.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    // Grant this session access to the protected page
    Guard::grant($protectedPage);

    // Finally, visit the page
    Response::go($targetPage->url());
};
