<?php

namespace JohannSchopplich;

use Kirby\Cms\App;
use Kirby\Cms\Page;

final class LockedPages
{
    public const SESSION_KEY = 'johannschopplich.locked-pages.access';

    /**
     * Check if a page is locked and user doesn't have access
     */
    public static function isLocked(Page|null $page): bool
    {
        if (!$page) {
            return false;
        }

        if ($page->isDraft() || $page->isErrorPage()) {
            return false;
        }

        $protectedPage = static::find($page);
        if (!$protectedPage) {
            return false;
        }

        // Check if user has valid access to this protected page
        $access = App::instance()->session(['long' => true])->data()->get(static::SESSION_KEY, []);
        $currentPassword = $protectedPage->lockedPagesPassword()->value();

        foreach ($access as $entry) {
            // Handle both old format (string) and new format (array) for backward compatibility
            if (is_string($entry)) {
                continue;
            }

            if (
                is_array($entry) &&
                isset($entry['uri'], $entry['password_hash']) &&
                $entry['uri'] === $protectedPage->uri() &&
                password_verify($currentPassword, $entry['password_hash'])
            ) {
                return false;
            }
        }

        return true;
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
            return static::find($parent);
        }

        return null;
    }

    /**
     * Clean up session data and remove invalid entries
     */
    public static function cleanupSessionData(): void
    {
        $session = App::instance()->session(['long' => true]);
        $access = $session->data()->get(static::SESSION_KEY, []);

        // Filter out old string format entries and invalid data
        $cleanAccess = array_filter($access, function ($entry) {
            return is_array($entry) &&
                   isset($entry['uri'], $entry['password_hash']) &&
                   is_string($entry['uri']) &&
                   is_string($entry['password_hash']);
        });

        // Re-index array to avoid gaps
        $cleanAccess = array_values($cleanAccess);

        $session->data()->set(static::SESSION_KEY, $cleanAccess);
    }

    /**
     * Revoke access for a specific page URI
     */
    public static function revokeAccess(string $uri): void
    {
        $session = App::instance()->session(['long' => true]);
        $access = $session->data()->get(static::SESSION_KEY, []);

        $access = array_filter($access, function ($entry) use ($uri) {
            return !(is_array($entry) && isset($entry['uri']) && $entry['uri'] === $uri);
        });

        $session->data()->set(static::SESSION_KEY, array_values($access));
    }
}
