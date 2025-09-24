<?php

use JohannSchopplich\LockedPages;

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
    if (!LockedPages::isLocked($targetPage)) {
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

    $protectedPage = LockedPages::find($targetPage);

    // Verify entered password
    if ($protectedPage->lockedPagesPassword()->value() !== get('password')) {
        return [
            'error' => option('johannschopplich.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    // Get list of pages where logged in already
    $access = $kirby->session()->data()->get(LockedPages::SESSION_KEY, []);

    // Clean up old format entries and entries for the same URI
    $access = array_filter($access, function ($entry) use ($protectedPage) {
        // Remove old string format entries
        if (is_string($entry)) {
            return false;
        }
        // Remove existing entries for the same URI (to update with new password hash)
        if (is_array($entry) && isset($entry['uri']) && $entry['uri'] === $protectedPage->uri()) {
            return false;
        }
        return true;
    });

    // Add new access entry with structured data
    $access[] = [
        'uri' => $protectedPage->uri(),
        'password_hash' => password_hash(get('password'), PASSWORD_DEFAULT),
        'granted_at' => time()
    ];

    // Save access list
    $kirby->session()->data()->set(LockedPages::SESSION_KEY, $access);

    // Finally, visit the page
    go($uri);
};
