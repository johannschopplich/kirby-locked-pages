<?php

namespace KirbyExtended;

use Kirby\Cms\Page;

class LockedPage
{
    public static function isLocked(?Page $page): bool
    {
        if (!$page) {
            return false;
        }

        $protectedPage = static::find($page);
        if (!$protectedPage) {
            return false;
        }

        if (kirby()->session()->get("locked-pages.access.{$protectedPage->id()}", false)) {
            return false;
        }

        return true;
    }

    public static function find(Page $page): ?Page
    {
        if ($page->lockedPageEnabled()->toBool()) {
            return $page;
        }

        if ($parent = $page->parent()) {
            return static::find($parent);
        }

        return null;
    }
}
