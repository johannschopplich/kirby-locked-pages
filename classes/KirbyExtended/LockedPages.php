<?php

namespace KirbyExtended;

use Kirby\Cms\Page;

class LockedPages
{
    public static function isLocked(?Page $page): bool
    {
        if (!$page) {
            return false;
        }

        $protectedPage = static::findLockedPage($page);
        if (!$protectedPage) {
            return false;
        }

        if (kirby()->session()->get("locked-pages.access.{$protectedPage->id()}", false)) {
            return false;
        }

        return true;
    }

    public static function findLockedPage(Page $page): ?Page
    {
        if ($page->lockedPageEnabled()->toBool()) {
            return $page;
        }

        if ($parent = $page->parent()) {
            return static::findLockedPage($parent);
        }

        return null;
    }
}
