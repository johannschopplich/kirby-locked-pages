<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Data\Data;
use Kirby\Exception\NotFoundException;
use Kirby\Filesystem\F;
use Kirby\Http\Range;
use Kirby\Http\Response;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FilesTest extends LockedPagesTestCase
{
    private const CONTENT = __DIR__ . '/tmp/site/content';
    private const MEDIA = __DIR__ . '/tmp/site/media/pages';
    private const CACHE = __DIR__ . '/tmp/site/site/cache/locked-pages';

    /**
     * Writes the pages to disk, since serving a file needs a real one.
     */
    protected function app(array $props = []): App
    {
        $this->writePage('notes', ['Title' => 'Notes', 'LockedPagesEnable' => 'true', 'LockedPagesPassword' => 's3cret'], 'painting.jpg');
        $this->writePage('notes/hello', ['Title' => 'Hello'], 'sketch.jpg');
        $this->writePage('open', ['Title' => 'Open'], 'photo.jpg');

        return parent::app(['site' => [], ...$props]);
    }

    private function writePage(string $id, array $content, string|null $imageFilename = null): void
    {
        Data::write(self::CONTENT . '/' . $id . '/default.txt', $content);

        if ($imageFilename !== null) {
            $image = imagecreatetruecolor(40, 30);
            imagejpeg($image, self::CONTENT . '/' . $id . '/' . $imageFilename);
        }
    }

    private function path(string $url): string
    {
        return substr($url, strlen('https://example.com/'));
    }

    private function sentBody(Response $response): string
    {
        ob_start();
        $response->send();

        return ob_get_clean();
    }

    #[Test]
    public function serves_a_locked_file_once_its_page_is_unlocked(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));

        $response = $app->call($this->path($app->file('notes/painting.jpg')->url()));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->code());
        $this->assertSame('image/jpeg', $response->type());
        $this->assertSame('no-store, private', $response->header('Cache-Control'));
    }

    #[Test]
    public function streams_a_locked_file_larger_than_the_memory_limit(): void
    {
        $video = self::CONTENT . '/notes/making-of.mp4';
        F::write($video, str_repeat(random_bytes(1024), 32 * 1024));
        $app = $this->app();
        Guard::grant($app->page('notes'));
        ini_set('memory_limit', (string)(memory_get_usage() + 16 * 1024 * 1024));

        $response = $app->call($this->path($app->file('notes/making-of.mp4')->url()));
        $hash = hash_init('sha256');
        ob_start(function (string $chunk) use ($hash) {
            hash_update($hash, $chunk);
            return '';
        }, 65536);
        $response->send();
        ob_end_clean();

        $this->assertSame(hash_file('sha256', $video), hash_final($hash));
    }

    #[Test]
    public function streams_the_requested_byte_range_of_a_locked_file(): void
    {
        if (!class_exists(Range::class)) {
            $this->markTestSkipped('Kirby 5.3 added byte ranges.');
        }

        $app = $this->app(['server' => ['HTTP_RANGE' => 'bytes=0-9']]);
        Guard::grant($app->page('notes'));
        $file = $app->file('notes/painting.jpg');

        $response = $app->call($this->path($file->url()));

        $this->assertSame(206, $response->code());
        $this->assertSame('bytes 0-9/' . $file->size(), $response->header('Content-Range'));
        $this->assertSame('10', $response->header('Content-Length'));
        $this->assertSame(substr(F::read($file->root()), 0, 10), $this->sentBody($response));
    }

    #[Test]
    public function answers_an_unsatisfiable_byte_range_with_416(): void
    {
        if (!class_exists(Range::class)) {
            $this->markTestSkipped('Kirby 5.3 added byte ranges.');
        }

        $app = $this->app(['server' => ['HTTP_RANGE' => 'bytes=999999-']]);
        Guard::grant($app->page('notes'));

        $response = $app->call($this->path($app->file('notes/painting.jpg')->url()));

        $this->assertSame(416, $response->code());
        $this->assertSame('', $this->sentBody($response));
    }

    #[Test]
    public function does_not_find_a_locked_file_without_a_grant(): void
    {
        $app = $this->app();

        $this->assertFalse($app->call($this->path($app->file('notes/painting.jpg')->url())));
    }

    #[Test]
    public function changing_the_password_revokes_access_to_a_locked_file(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));
        $url = $app->file('notes/painting.jpg')->url();

        $app->impersonate('kirby', fn () => $app->page('notes')->update(['lockedPagesPassword' => 'changed']));

        $this->assertFalse($app->call($this->path($url)));
    }

    #[Test]
    public function locks_the_files_of_a_child_page(): void
    {
        $app = $this->app();

        $this->assertFalse($app->call($this->path($app->file('notes/hello/sketch.jpg')->url())));
    }

    #[Test]
    public function keeps_an_open_file_in_the_media_folder(): void
    {
        $file = $this->app()->file('open/photo.jpg');

        $this->assertSame($file->mediaUrl(), $file->url());
    }

    #[Test]
    public function rejects_a_filename_that_leaves_the_thumb_folder(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));
        $file = $app->file('notes/painting.jpg');
        $file->resize(20);

        $response = $app->call('locked/files/notes/' . $file->mediaHash() . '/' . rawurlencode('../../../../../content/notes/default.txt'));

        $this->assertFalse($response);
    }

    #[Test]
    public function does_not_find_an_unknown_thumb_of_a_locked_file(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));
        $file = $app->file('notes/painting.jpg');

        $this->assertFalse($app->call('locked/files/notes/' . $file->mediaHash() . '/painting-99x.jpg'));
    }

    #[Test]
    public function redirects_an_outdated_hash_of_a_locked_file_to_its_current_url(): void
    {
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');

        $response = $app->call('locked/files/notes/' . $file->mediaToken() . '-1/painting.jpg');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(307, $response->code());
        $this->assertSame($file->url(), $response->header('Location'));
    }

    #[Test]
    public function does_not_redirect_a_hash_with_a_foreign_token(): void
    {
        $app = $this->app();

        $this->assertFalse($app->call('locked/files/notes/0000000000-1/painting.jpg'));
    }

    #[Test]
    public function does_not_serve_an_xhtml_file_of_a_locked_page(): void
    {
        F::write(self::CONTENT . '/notes/page.xhtml', '<script>alert(1)</script>');
        $app = $this->app();
        Guard::grant($app->page('notes'));

        $this->assertFalse($app->call($this->path($app->file('notes/page.xhtml')->url())));
    }

    #[Test]
    public function redirects_the_clean_url_of_a_locked_file_with_content_file_redirects(): void
    {
        $app = $this->app(['options' => ['content' => ['fileRedirects' => true]]]);

        $response = $app->call('notes/painting.jpg');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($app->file('notes/painting.jpg')->url(), $response->header('Location'));
    }

    #[Test]
    public function serves_a_thumb_of_a_locked_file_from_outside_the_media_folder(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));
        $thumb = $app->file('notes/painting.jpg')->resize(20);

        $response = $app->call($this->path($thumb->url()));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(20, getimagesizefromstring($this->sentBody($response))[0]);
        $this->assertDirectoryDoesNotExist(self::MEDIA . '/notes');
    }

    #[Test]
    public function does_not_generate_a_thumb_of_a_locked_file_without_a_grant(): void
    {
        $app = $this->app();
        $thumb = $app->file('notes/painting.jpg')->resize(20);

        $this->assertFalse($app->call($this->path($thumb->url())));
        $this->assertFileDoesNotExist($thumb->root());
    }

    #[Test]
    public function returns_the_original_when_the_thumb_job_cannot_be_written(): void
    {
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');
        F::write(self::CACHE . '/.keep', '');
        chmod(self::CACHE, 0o555);

        try {
            $thumb = $file->resize(20);
        } finally {
            chmod(self::CACHE, 0o755);
        }

        $this->assertSame($file->url(), $thumb->url());
    }

    #[Test]
    public function resize_removes_the_thumbs_of_an_earlier_file_version(): void
    {
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');
        $file->resize(20);
        $outdatedHash = $file->mediaHash();

        touch($file->root(), time() + 10);
        clearstatcache();
        $file->resize(20);

        $this->assertDirectoryDoesNotExist(self::CACHE . '/notes/' . $outdatedHash);
    }

    #[Test]
    public function deleting_a_locked_file_removes_its_thumbs(): void
    {
        $this->writePage('notes', ['Title' => 'Notes', 'LockedPagesEnable' => 'true', 'LockedPagesPassword' => 's3cret'], 'study.jpg');
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');
        $file->resize(20);
        $study = $app->file('notes/study.jpg');
        $study->resize(20);

        $app->impersonate('kirby', fn () => $file->delete());

        $this->assertSame([self::CACHE . '/notes/' . $study->mediaHash()], glob(self::CACHE . '/notes/*-*'));
    }

    #[Test]
    public function deleting_a_locked_page_removes_its_thumbs(): void
    {
        $app = $this->app();
        $app->file('notes/hello/sketch.jpg')->resize(20);

        $app->impersonate('kirby', fn () => $app->page('notes/hello')->delete());

        $this->assertDirectoryDoesNotExist(self::CACHE . '/notes/hello');
    }

    #[Test]
    public function serves_a_locked_file_to_a_user_who_may_read_it(): void
    {
        $app = $this->app(['users' => [['email' => 'editor@example.com', 'role' => 'admin']]]);
        $app->impersonate('editor@example.com');

        $response = $app->call($this->path($app->file('notes/painting.jpg')->url()));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->code());
        $this->assertTrue(Guard::isLocked($app->page('notes')));
    }

    #[Test]
    public function does_not_serve_a_locked_file_to_a_user_who_may_not_read_it(): void
    {
        $app = $this->app([
            'blueprints' => ['users/member' => ['name' => 'member', 'permissions' => ['files' => ['read' => false]]]],
            'users' => [['email' => 'member@example.com', 'role' => 'member']],
        ]);
        $app->impersonate('member@example.com');

        $this->assertFalse($app->call($this->path($app->file('notes/painting.jpg')->url())));
    }

    #[Test]
    public function blocks_the_media_route_for_a_locked_file(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));

        try {
            $app->call($this->path($app->file('notes/painting.jpg')->mediaUrl()));
            $this->fail('The media route published a locked file.');
        } catch (NotFoundException) {
        }

        $this->assertDirectoryDoesNotExist(self::MEDIA . '/notes');
    }

    #[Test]
    public function url_removes_a_copy_published_before_the_lock(): void
    {
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');
        F::write($file->mediaRoot(), 'published');

        $file->url();

        $this->assertFileDoesNotExist($file->mediaRoot());
    }

    #[Test]
    public function resize_removes_a_copy_published_before_the_lock(): void
    {
        $app = $this->app();
        $file = $app->file('notes/painting.jpg');
        F::write($file->mediaRoot(), 'published');

        $file->resize(20);

        $this->assertFileDoesNotExist($file->mediaRoot());
    }

    #[Test]
    public function enabling_protection_removes_the_media_folder_of_the_page(): void
    {
        $app = $this->app();
        $app->call($this->path($app->file('open/photo.jpg')->mediaUrl()));
        F::write(self::MEDIA . '/open/child/1234567890-1/sketch.jpg', 'published');

        $app->impersonate('kirby', fn () => $app->page('open')->update(['lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret']));

        $this->assertDirectoryDoesNotExist(self::MEDIA . '/open');
    }

    #[Test]
    public function publishing_a_protected_draft_removes_its_media_folder(): void
    {
        Data::write(self::CONTENT . '/_drafts/wip/default.txt', ['Title' => 'WIP', 'LockedPagesEnable' => 'true', 'LockedPagesPassword' => 's3cret']);
        F::write(self::MEDIA . '/wip/1234567890-1/photo.jpg', 'published');
        $app = $this->app();

        $app->impersonate('kirby', fn () => $app->site()->draft('wip')->changeStatus('unlisted'));

        $this->assertDirectoryDoesNotExist(self::MEDIA . '/wip');
    }
}
