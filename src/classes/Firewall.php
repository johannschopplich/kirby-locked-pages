<?php

declare(strict_types = 1);

namespace JohannSchopplich\LockedPages;

use Kirby\Cms\App;
use Kirby\Cms\File;
use Kirby\Cms\FileRules;
use Kirby\Cms\FileVersion;
use Kirby\Cms\Media;
use Kirby\Cms\Page;
use Kirby\Data\Data;
use Kirby\Filesystem\Asset;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Kirby\Filesystem\Filename;
use Kirby\Http\Response;
use Kirby\Http\Route;
use Throwable;

/**
 * Keeps the files of locked pages out of the public media folder.
 *
 * Kirby copies a file to `media/` on its first request, and the web server
 * serves the copy from then on without asking PHP. A file of a locked page
 * is served through the plugin's route instead, which checks access on
 * every request, and its thumbs are generated into the cache root.
 */
final class Firewall
{
    public static function url(File $file): string
    {
        $page = $file->page();

        if (!Guard::isProtected($page)) {
            return $file->mediaUrl();
        }

        self::unpublish($page);

        return self::baseUrl($file) . '/' . $file->filename();
    }

    /**
     * Returns a thumb of a locked file, generated on its first request.
     */
    public static function version(App $kirby, File|Asset $file, array $options): File|Asset|FileVersion
    {
        if (!$file instanceof File || !$file->isResizable() || !Guard::isProtected($file->page())) {
            return $kirby->nativeComponent('file::version')($kirby, $file, $options);
        }

        self::unpublish($file->page());

        $root = self::versionsRoot($file);
        $filename = basename((new Filename($file->root(), '{{ name }}{{ attributes }}.{{ extension }}', $options))->toString());
        $job = $root . '/.jobs/' . $filename . '.json';

        if (!F::exists($root . '/' . $filename) && !F::exists($job)) {
            Media::unpublish(self::pageVersionsRoot($file->page()), $file, $root);

            try {
                Data::write($job, [...$options, 'filename' => $file->filename()]);
            } catch (Throwable) {
                return $file;
            }
        }

        return new FileVersion([
            'original' => $file,
            'modifications' => $options,
            'root' => $root . '/' . $filename,
            'url' => self::baseUrl($file) . '/' . $filename
        ]);
    }

    /**
     * Serves a file of a locked page, or one of its thumbs, to a session
     * that unlocked the page or a user who may read the file in the Panel.
     */
    public static function response(string $pageId, string $hash, string $filename): Response|false
    {
        $page = App::instance()->page($pageId);
        $filename = urldecode($filename);

        if (!Guard::isProtected($page) || basename($filename) !== $filename || in_array($filename, ['.', '..'], true)) {
            return false;
        }

        $originalFile = $page->files()->filter(fn (File $file) => $file->mediaHash() === $hash)->first();

        if (!$originalFile) {
            // Like Kirby's media route, an outdated hash with the file's
            // token redirects to the current URL.
            $file = $page->file($filename);

            return $file && str_starts_with($hash, $file->mediaToken() . '-')
                ? Response::redirect(self::url($file), 307)
                : false;
        }

        if (Guard::isLocked($page) && !$originalFile->isReadable()) {
            return false;
        }

        // Never serve risky files (e.g. HTML or PHP), which Kirby refuses to
        // publish to the media folder.
        try {
            FileRules::validFile($originalFile, false);
        } catch (Throwable) {
            return false;
        }

        $headers = ['Cache-Control' => 'no-store, private'];

        if ($originalFile->filename() === $filename) {
            return new FileStream($originalFile->root(), $headers);
        }

        $root = self::versionsRoot($originalFile);
        $thumb = $root . '/' . $filename;

        if (!F::exists($thumb)) {
            $job = $root . '/.jobs/' . $filename . '.json';

            try {
                $options = Data::read($job);
            } catch (Throwable) {
                return false;
            }

            try {
                App::instance()->thumb($originalFile->root(), $thumb, $options);
                F::remove($job);
            } catch (Throwable $exception) {
                F::remove($thumb);
                throw $exception;
            }
        }

        return new FileStream($thumb, $headers);
    }

    /**
     * Checks whether a route is Kirby's media route for a file of a locked
     * page, which would publish the file. Without `content.salt`, anyone who
     * knows the server path of the content folder can compute the URL.
     */
    public static function blocksMediaRoute(Route $route): bool
    {
        if ($route->env() !== 'media' || !str_ends_with($route->pattern(), $route->regex('/pages/(:all)/(:any)/(:any)'))) {
            return false;
        }

        return Guard::isProtected(App::instance()->page($route->arguments()[0]));
    }

    /**
     * Removes the copies of a page's files, and of its subpages' files, that
     * Kirby published to the media folder before the page became protected.
     * The copies of draft subpages go too; Kirby publishes them again on
     * their next request.
     */
    public static function unpublish(Page $page): void
    {
        Dir::remove($page->mediaRoot());
    }

    /**
     * Removes the thumbs of a file, or of every file of a page and its
     * subpages.
     */
    public static function removeVersions(File|Page $model): void
    {
        if ($model instanceof Page) {
            Dir::remove(self::pageVersionsRoot($model));
        } elseif ($page = $model->page()) {
            Media::unpublish(self::pageVersionsRoot($page), $model);
        }
    }

    private static function baseUrl(File $file): string
    {
        $kirby = App::instance();

        return $kirby->url() . '/' . $kirby->option('johannschopplich.locked-pages.slug') . '/files/' . $file->page()->id() . '/' . $file->mediaHash();
    }

    private static function versionsRoot(File $file): string
    {
        return self::pageVersionsRoot($file->page()) . '/' . $file->mediaHash();
    }

    private static function pageVersionsRoot(Page $page): string
    {
        return App::instance()->root('cache') . '/locked-pages/' . $page->id();
    }
}
