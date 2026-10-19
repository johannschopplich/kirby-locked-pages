<?php

declare(strict_types = 1);

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use PHPUnit\Framework\TestCase;

abstract class LockedPagesTestCase extends TestCase
{
    /**
     * A locked `notes` subtree (child `hello` inherits the lock), an open
     * page, plus a locked draft and locked error page to prove the drafts
     * and error page are never locked regardless of their own settings.
     */
    protected const SITE = [
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

    protected function tearDown(): void
    {
        App::destroy();
        Dir::remove(__DIR__ . '/tmp/site');
    }

    protected function app(array $props = []): App
    {
        $props['site'] ??= self::SITE;

        return new App(array_replace_recursive([
            'roots' => [
                'index' => __DIR__ . '/tmp/site',
                // Outside the site's root, since Kirby writes the session on shutdown, after `tearDown()` removed it.
                'sessions' => __DIR__ . '/tmp/sessions',
            ],
            'urls' => ['index' => 'https://example.com'],
        ], $props));
    }
}
