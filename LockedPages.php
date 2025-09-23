<?php

namespace JohannSchopplich;

use Kirby\Cms\App;
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
        
        // check if Session has URL/password hash
        $access = App::instance()->session(['long' => true])->data()->get(LockedPages::SESSION_KEY, []);
        foreach($access as $index => $entry) {
          $urlpass = explode("|", $entry, 2);
          if ( $urlpass[0] == $protectedPage->uri() && password_verify($protectedPage->lockedPagesPassword()->value(), $urlpass[1]) ) {
            return false;
          }
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
