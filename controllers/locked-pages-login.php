<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;

return function (\Kirby\Cms\App $kirby) {
    $uri = get('redirect');
    $targetPage = page($uri);

    // Ensure target page exists
    if ($targetPage === null) {
        return [
            'error' => false
        ];
    }

    // If page is not locked or user has access already, just go to the page
    if (!Guard::isLocked($targetPage)) {
        go($uri);
    }

    // Ensure it's a POST request
    if (!$kirby->request()->is('POST')) {
        return [
            'error' => false
        ];
    }

    $csrfToken = get('csrf');

    // Verify the token of the form
    if (csrf($csrfToken) === false) {
        return [
            'error' => option('johannschopplich.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    $protectedPage = Guard::find($targetPage);

    // Verify entered password
    if ($protectedPage->lockedPagesPassword()->value() !== get('password')) {
        return [
            'error' => option('johannschopplich.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    // Grant this session access to the protected page
    Guard::grant($protectedPage);

    // Finally, visit the page
    go($uri);
};
