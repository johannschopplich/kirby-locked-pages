<?php

declare(strict_types = 1);

namespace JohannSchopplich\LockedPages;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Session\Session;

final class Guard
{
    public const SESSION_KEY = 'johannschopplich.locked-pages.access';

    /**
     * Return the session used to store access grants.
     *
     * All grant reads and writes go through this single accessor so the
     * session scope stays consistent. Defaults to a long (2-week, no idle
     * timeout) session; set `johannschopplich.locked-pages.longSession`
     * to false for a normal short session.
     */
    public static function session(): Session
    {
        return App::instance()->session([
            'long' => (bool)option('johannschopplich.locked-pages.longSession', true)
        ]);
    }

    /**
     * Check if a page is locked and the current session has no access
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

        $access = self::session()->data()->get(self::SESSION_KEY, []);
        $granted = $access[$protectedPage->id()] ?? null;

        // A grant stays valid only while it matches the current password, so
        // changing the password in the Panel revokes every existing grant
        return !is_string($granted) || !hash_equals($granted, self::passwordHash($protectedPage));
    }

    /**
     * Find the protected page in the page hierarchy
     */
    public static function find(Page $page): Page|null
    {
        if ($page->lockedPagesEnable()->exists() && $page->lockedPagesEnable()->isTrue()) {
            return $page;
        }

        if ($parent = $page->parent()) {
            return self::find($parent);
        }

        return null;
    }

    /**
     * Grant the current session access to a protected page.
     *
     * Grants are keyed by the language-independent page ID, so unlocking a
     * page applies across all of its translations.
     */
    public static function grant(Page $protectedPage): void
    {
        $session = self::session();
        $access = $session->data()->get(self::SESSION_KEY, []);
        $access[$protectedPage->id()] = self::passwordHash($protectedPage);
        $session->data()->set(self::SESSION_KEY, $access);
    }

    /**
     * Hash a protected page's current password for grant comparison.
     *
     * The password is an editor-visible shared secret already stored in
     * plaintext, and the hash never leaves the server-side session, so a
     * fast hash is sufficient – bcrypt would only add per-request cost.
     */
    private static function passwordHash(Page $protectedPage): string
    {
        return hash('sha256', (string)$protectedPage->lockedPagesPassword()->value());
    }
}
