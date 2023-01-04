<?php

namespace JohannSchopplich;

use Kirby\Cms\Page;

final class LockedPages
{
    public const SESSION_KEY = 'johannschopplich.locked-pages.access';

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

        $access = kirby()->session()->data()->get(LockedPages::SESSION_KEY, []);
        if (in_array($protectedPage->uri(), $access)) {
            return false;
        }

        return true;
    }

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
}
