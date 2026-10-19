<?php

declare(strict_types = 1);

namespace JohannSchopplich\LockedPages;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Filesystem\F;
use Kirby\Session\Session;

final class Guard
{
    public const SESSION_KEY = 'johannschopplich.locked-pages.access';

    /**
     * Returns the session that holds access grants, as a plain read: asking
     * for a long session here would turn any existing session, including a
     * Panel login, into a long one.
     */
    public static function session(): Session
    {
        return App::instance()->session();
    }

    /**
     * Checks whether a page is locked and the current session holds no grant.
     */
    public static function isLocked(Page|null $page): bool
    {
        if (!$page) {
            return false;
        }

        if ($page->isDraft() || $page->isErrorPage()) {
            return false;
        }

        $protectedPage = self::find($page);
        if (!$protectedPage) {
            return false;
        }

        $grants = self::session()->data()->get(self::SESSION_KEY, []);
        $grant = $grants[$protectedPage->id()] ?? null;

        // A grant stays valid only while it matches the current password, so
        // changing the password in the Panel revokes every existing grant.
        return !is_string($grant) || !hash_equals($grant, self::grantHash($protectedPage));
    }

    /**
     * Walks up the page hierarchy to the nearest protected page.
     */
    public static function find(Page $page): Page|null
    {
        if ($page->lockedPagesEnable()->toBool()) {
            return $page;
        }

        if ($parent = $page->parent()) {
            return self::find($parent);
        }

        return null;
    }

    /**
     * Resolves the page owning a non-Page route result from its path.
     *
     * Content representations (`page.json`, `.xml`, `.rss`, …) resolve to a
     * `Response` rather than a `Page`, so `route:after` only has the request
     * path to work with. A language's path can be empty (the usual
     * default-language case), so the prefix is only stripped when there is one.
     */
    public static function resolveFromRoutePath(string $path): Page|null
    {
        $kirby = App::instance();

        if ($kirby->multilang()) {
            $prefix = $kirby->language()?->path() ?? '';
            if ($prefix !== '' && str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix) + 1);
            }
        }

        $extension = F::extension($path);
        if ($extension === '' || $extension === 'html') {
            return null;
        }

        return $kirby->page(substr($path, 0, -(strlen($extension) + 1)));
    }

    /**
     * Compares a submitted password against the page's stored password.
     *
     * The comparison is constant-time, and an empty stored password fails
     * closed so a page that enables protection without setting a password
     * stays locked.
     */
    public static function verify(Page $protectedPage, string|null $submittedPassword): bool
    {
        $storedPassword = (string)$protectedPage->lockedPagesPassword()->value();

        return $storedPassword !== '' && hash_equals($storedPassword, (string)$submittedPassword);
    }

    /**
     * Grants the current session access to a protected page.
     *
     * Grants are keyed by the language-independent page ID, so unlocking a
     * page applies across all of its translations. Unless the `longSession`
     * option is off, the session becomes a long one (two weeks, no idle
     * timeout).
     */
    public static function grant(Page $protectedPage): void
    {
        $kirby = App::instance();
        $session = $kirby->session([
            'long' => (bool)$kirby->option('johannschopplich.locked-pages.longSession')
        ]);
        $grants = $session->data()->get(self::SESSION_KEY, []);
        $grants[$protectedPage->id()] = self::grantHash($protectedPage);
        $session->data()->set(self::SESSION_KEY, $grants);
    }

    /**
     * Derives the session grant token from a protected page's current password.
     *
     * The password is an editor-visible shared secret already stored in
     * plaintext, and the hash never leaves the server-side session, so a
     * fast hash is sufficient – bcrypt would only add per-request cost.
     */
    private static function grantHash(Page $protectedPage): string
    {
        return hash('sha256', (string)$protectedPage->lockedPagesPassword()->value());
    }
}
