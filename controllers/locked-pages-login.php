<?php

use KirbyExtended\LockedPages;

return function ($kirby) {
    $id = get('redirect');
    $targetPage = page($id);

    // Make sure page with given id exists
    if ($targetPage === null) {
        return [
            'error' => false
        ];
    }

    $protectedPage = LockedPages::find($targetPage);

    // If page or one of its parent isn't locked or the user has entered
    // the password this session already, visit the page immediately
    if ($protectedPage === null) {
        go($id);
    }

    // Make sure this is a post request
    if (!$kirby->request()->is('POST')) {
        return [
            'error' => false
        ];
    }

    $csrfToken = get('csrf');

    // Verify token of form
    if (csrf($csrfToken) === false) {
        return [
            'error' => option('kirby-extended.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    // Verify entered password
    if ($protectedPage->lockedPagesPassword()->value() !== get('password')) {
        return [
            'error' => option('kirby-extended.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    // Redirect future requests immediately for this session
    $kirby->session()->set("locked-pages.access.{$protectedPage->id()}", true);

    // Finally, visit the page
    go($id);
};
