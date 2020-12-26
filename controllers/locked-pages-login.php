<?php

use KirbyExtended\LockedPages;

return function ($kirby) {
    $id = get('redirect');
    $targetPage = page($id);

    $protectedPage = LockedPages::find($targetPage);
    if (!$protectedPage) {
        go($id);
    }

    if (!$kirby->request()->is('POST')) {
        return [
            'error' => false
        ];
    }

    $csrfToken = get('csrf');
    if (!csrf($csrfToken)) {
        return [
            'error' => option('kirby-extended.locked-pages.error.csrf', 'The CSRF token is invalid')
        ];
    }

    if ($protectedPage->lockedPagesPassword()->value() !== get('password')) {
        return [
            'error' => option('kirby-extended.locked-pages.error.password', 'The password is incorrect')
        ];
    }

    $kirby->session()->set("locked-pages.access.{$protectedPage->id()}", true);
    go($id);
};
