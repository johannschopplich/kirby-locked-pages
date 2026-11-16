<?php

use JohannSchopplich\LockedPages\Firewall;
use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Url;
use Kirby\Exception\NotFoundException;
use Kirby\Http\Response;
use Kirby\Http\Route;

$unpublishProtected = function (Page $newPage) {
    if (Guard::isProtected($newPage)) {
        Firewall::unpublish($newPage);
    }
};

return [
    'route:before' => function (Route $route) {
        // Kirby's event loop protection stays set after a throw, so a second
        // `$kirby->call()` in the same instance skips this hook – fine for
        // HTTP, where every request builds its own instance.
        if (Firewall::blocksMediaRoute($route)) {
            throw new NotFoundException();
        }
    },

    'route:after' => function (Route $route, string $path, string $method, $result, bool $final) {
        // Kirby answers a CORS preflight itself, without content.
        if (!$final || ($method === 'OPTIONS' && $result instanceof Response)) {
            return;
        }

        // With `content.fileRedirects`, Kirby redirects a file's clean URL to
        // its media URL, which the firewall blocks for a locked file.
        if ($result instanceof File && Guard::isProtected($result->page())) {
            return Response::redirect($result->url(), 307);
        }

        $page = $result instanceof Page ? $result : Guard::resolveFromRoute($path, $result);

        if (!Guard::isLocked($page)) {
            return;
        }

        $kirby = App::instance();
        $loginUrl = $kirby->site()->url() . '/' . $kirby->option('johannschopplich.locked-pages.slug');

        return Response::redirect(Url::to($loginUrl, [
            'query' => ['redirect' => $page->uri()]
        ]));
    },

    'page.update:after' => $unpublishProtected,
    'page.changeStatus:after' => $unpublishProtected,

    'file.changeName:after' => fn (File $newFile, File $oldFile) => Firewall::removeVersions($oldFile),
    'file.delete:after' => fn ($status, File $file) => Firewall::removeVersions($file),
    // A slug of a secondary language leaves the page ID, and so its thumbs, as they are.
    'page.changeSlug:after' => function (Page $newPage, Page $oldPage) {
        if ($newPage->id() !== $oldPage->id()) {
            Firewall::removeVersions($oldPage);
        }
    },
    'page.delete:after' => fn ($status, Page $page) => Firewall::removeVersions($page),
    'page.move:after' => fn (Page $newPage, Page $oldPage) => Firewall::removeVersions($oldPage),

    'locked-pages.logout' => function () {
        Guard::session()->data()->remove(Guard::SESSION_KEY);
    }
];
