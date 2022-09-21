<?php

use JohannSchopplich\LockedPages;

return function (\Kirby\Cms\App $kirby) {
    $id = get('redirect');
    $targetPage = page($id);

    // Make sure page with given id exists
    if ($targetPage === null) {
        return [
            'error' => false
        ];
    }

    // If page or one of its parent isn't locked or the user has entered
    // the password this session already, visit the page immediately
    if (!LockedPages::isLocked($targetPage)) {
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
            'error' => option('johannschopplich.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    $protectedPage = LockedPages::find($targetPage);

    // Verify entered password
    if ($protectedPage->lockedPagesPassword()->value() !== get('password')) {
        return [
            'error' => option('johannschopplich.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    // Get list of pages where logged in already
    $access = $kirby->session()->data()->pull(LockedPages::SESSION_KEY, []);

    // Redirect future requests to this page id immediately for this session
    $access[] = $protectedPage->id();

    // Save access list
    $kirby->session()->data()->set(LockedPages::SESSION_KEY, $access);

    // Finally, visit the page
    go($id);
};
