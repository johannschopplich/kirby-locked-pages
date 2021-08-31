<?php

namespace KirbyExtended;

use Kirby\Cms\Page;

final class LockedPages
{
    public const SESSION_KEY = 'kirby-extended.locked-pages.access';

    public static function isLocked(?Page $page): bool
    {
        if ($page === null) {
            return false;
        }

        if ($page->isDraft() || $page->isErrorPage()) {
            return false;
        }

        $protectedPage = static::find($page);
        if ($protectedPage === null) {
            return false;
        }

        $access = kirby()->session()->data()->get(LockedPages::SESSION_KEY, []);
        if (isset($access[$protectedPage->id()])) {
            return false;
        }

        return true;
    }

    public static function find(Page $page): ?Page
    {
        if ($page->lockedPagesEnable()->exists() && $page->lockedPagesEnable()->toBool()) {
            return $page;
        }

        if ($parent = $page->parent()) {
            return static::find($parent);
        }

        return null;
    }

    public static function routeHook($route, $path, $method, $result, $final): void {
        if (!$final) return;
        if (!is_a($result, \Kirby\Cms\Page::class)) return;
        if (!\KirbyExtended\LockedPages::isLocked($result)) return;

        $slug = option('kirby-extended.locked-pages.slug', 'locked');
        $options = [
            'query' => ['redirect' => $result->id()]
        ];

        go(url($slug, $options));
    }

    public static function logoutHook(): void
    {
        kirby()->session()->data()->remove(LockedPages::SESSION_KEY);
    }
}
