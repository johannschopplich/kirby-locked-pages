<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GuardTest extends TestCase
{
    protected function tearDown(): void
    {
        App::destroy();
    }

    private function createApp(array $props = []): App
    {
        $base = sys_get_temp_dir() . '/kirby-locked-pages-tests';
        $props['site'] ??= self::SITE;

        return new App(array_replace_recursive([
            'roots' => [
                'index' => $base,
                'sessions' => $base . '/sessions',
            ],
            'urls' => ['index' => 'https://example.com'],
        ], $props));
    }

    /**
     * A locked `notes` subtree (child `hello` inherits the lock), an open
     * page, plus a locked draft and locked error page to prove the drafts
     * and error page are never locked regardless of their own settings.
     */
    private const SITE = [
        'children' => [
            [
                'slug' => 'notes',
                'content' => ['title' => 'Notes', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret'],
                'children' => [
                    ['slug' => 'hello', 'content' => ['title' => 'Hello']],
                ],
            ],
            ['slug' => 'open', 'content' => ['title' => 'Open']],
            ['slug' => 'error', 'content' => ['title' => 'Error', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret']],
        ],
        'drafts' => [
            ['slug' => 'wip', 'content' => ['title' => 'WIP', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret']],
        ],
    ];

    /** @return array<string, array{0: string, 1: string|null, 2: bool}> */
    public static function passwordCases(): array
    {
        return [
            'correct password' => ['s3cret', 's3cret', true],
            'wrong password' => ['s3cret', 'nope', false],
            'empty submission' => ['s3cret', '', false],
            'null submission' => ['s3cret', null, false],
            'empty stored password fails closed' => ['', 's3cret', false],
        ];
    }

    #[Test]
    #[DataProvider('passwordCases')]
    public function verifies_the_password_against_the_stored_secret(string $storedPassword, string|null $submittedPassword, bool $expected): void
    {
        $app = $this->createApp([
            'site' => [
                'children' => [
                    [
                        'slug' => 'secret',
                        'content' => ['lockedPagesEnable' => 'true', 'lockedPagesPassword' => $storedPassword],
                    ],
                ],
            ],
        ]);

        $this->assertSame($expected, Guard::verify($app->page('secret'), $submittedPassword));
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function findCases(): array
    {
        return [
            'self-locked page returns itself' => ['notes', 'notes'],
            'child inherits the locked parent' => ['notes/hello', 'notes'],
            'unlocked page returns null' => ['open', null],
        ];
    }

    #[Test]
    #[DataProvider('findCases')]
    public function find_walks_up_to_the_locked_ancestor(string $id, string|null $expected): void
    {
        $app = $this->createApp();

        $this->assertSame($expected, Guard::find($app->page($id))?->id());
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function lockStateCases(): array
    {
        return [
            'locked page with no grant is locked' => ['notes', true],
            'child of a locked page is locked' => ['notes/hello', true],
            'unlocked page is not locked' => ['open', false],
            'error page is never locked' => ['error', false],
        ];
    }

    #[Test]
    #[DataProvider('lockStateCases')]
    public function is_locked_reflects_the_page_lock_state(string $id, bool $expected): void
    {
        $app = $this->createApp();

        $this->assertSame($expected, Guard::isLocked($app->page($id)));
    }

    #[Test]
    public function a_draft_is_never_locked(): void
    {
        $app = $this->createApp();

        $this->assertFalse(Guard::isLocked($app->site()->draft('wip')));
    }

    #[Test]
    public function a_null_page_is_never_locked(): void
    {
        $this->createApp();

        $this->assertFalse(Guard::isLocked(null));
    }

    #[Test]
    public function granting_access_unlocks_the_page_and_its_subtree(): void
    {
        $app = $this->createApp();

        Guard::grant($app->page('notes'));

        $this->assertFalse(Guard::isLocked($app->page('notes')));
        $this->assertFalse(Guard::isLocked($app->page('notes/hello')));
    }

    #[Test]
    public function changing_the_password_revokes_an_existing_grant(): void
    {
        $app = $this->createApp();
        $notes = $app->page('notes');

        Guard::grant($notes);
        $this->assertFalse(Guard::isLocked($notes));

        // A password change in the Panel makes the stored grant hash stale,
        // which must lock the page again for the existing session
        $changedPage = $notes->clone([
            'content' => ['title' => 'Notes', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 'changed'],
        ]);

        $this->assertTrue(Guard::isLocked($changedPage));
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function routePathCases(): array
    {
        return [
            'child page representation' => ['notes/hello.json', 'notes/hello'],
            'root page representation' => ['notes.json', 'notes'],
            'path without an extension' => ['notes/hello', null],
            'html is not a representation' => ['notes.html', null],
            'non-page file' => ['robots.txt', null],
        ];
    }

    #[Test]
    #[DataProvider('routePathCases')]
    public function resolve_from_route_path_recovers_the_owning_page(string $path, string|null $expected): void
    {
        $this->createApp();

        $this->assertSame($expected, Guard::resolveFromRoutePath($path)?->id());
    }

    #[Test]
    public function a_locked_representation_is_resolved_and_locked_across_languages(): void
    {
        $app = $this->createApp([
            'languages' => [
                ['code' => 'en', 'default' => true, 'name' => 'English'],
                ['code' => 'de', 'name' => 'Deutsch'],
            ],
            'site' => [
                'children' => [
                    [
                        'slug' => 'secret',
                        'translations' => [
                            ['code' => 'en', 'content' => ['title' => 'Secret', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret']],
                            ['code' => 'de', 'content' => ['title' => 'Geheim', 'lockedPagesEnable' => 'true', 'lockedPagesPassword' => 's3cret']],
                        ],
                    ],
                ],
            ],
        ]);

        // Default language: no prefix, resolves straight to the page
        $this->assertSame('secret', Guard::resolveFromRoutePath('secret.json')?->id());

        // Non-default language: the `de/` prefix must be stripped, otherwise
        // the German representation would resolve to null and serve unprotected
        $app->setCurrentLanguage('de');
        $german = Guard::resolveFromRoutePath('de/secret.json');

        $this->assertSame('secret', $german?->id());
        $this->assertTrue(Guard::isLocked($german));
    }
}
