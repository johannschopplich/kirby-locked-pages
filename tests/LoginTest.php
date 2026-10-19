<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LoginTest extends LockedPagesTestCase
{
    private const CSRF_TOKEN = 'test-token';

    private function submit(array $body, string $redirect = 'notes/hello'): App
    {
        $app = $this->app([
            'request' => [
                'method' => 'POST',
                'query' => ['redirect' => $redirect],
                'body' => ['csrf' => self::CSRF_TOKEN, ...$body],
            ],
        ]);
        $app->session()->set('kirby.csrf', self::CSRF_TOKEN);

        return $app;
    }

    #[Test]
    public function redirects_a_locked_page_to_the_login_form(): void
    {
        $response = $this->app()->call('notes/hello');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(302, $response->code());
        $this->assertSame('https://example.com/locked?redirect=notes%2Fhello', $response->header('Location'));
    }

    #[Test]
    public function redirects_to_the_login_form_of_the_current_language(): void
    {
        $app = $this->app([
            'languages' => [
                ['code' => 'en', 'default' => true, 'name' => 'English'],
                ['code' => 'de', 'name' => 'Deutsch', 'url' => '/de'],
            ],
        ]);

        $response = $app->call('de/notes');

        $this->assertSame('https://example.com/de/locked?redirect=notes', $response->header('Location'));
    }

    #[Test]
    public function serves_an_open_page(): void
    {
        $result = $this->app()->call('open');

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame('open', $result->id());
    }

    #[Test]
    public function renders_the_login_form(): void
    {
        $app = $this->app(['request' => ['query' => ['redirect' => 'notes/hello']]]);

        $html = $app->call('locked');

        $this->assertStringContainsString('<title>Page locked</title>', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringNotContainsString('class="error"', $html);
    }

    #[Test]
    public function renders_the_login_form_in_the_current_language(): void
    {
        $app = $this->app([
            'languages' => [
                ['code' => 'en', 'default' => true, 'name' => 'English'],
                ['code' => 'de', 'name' => 'Deutsch', 'url' => '/de'],
            ],
            'request' => ['query' => ['redirect' => 'notes']],
        ]);

        $html = $app->call('de/locked');

        $this->assertStringContainsString('<title>Seite geschützt</title>', $html);
        $this->assertStringContainsString('Seite öffnen', $html);
    }

    #[Test]
    public function the_title_option_overrides_the_translation(): void
    {
        $app = $this->app([
            'options' => ['johannschopplich.locked-pages' => ['title' => 'Members only']],
            'request' => ['query' => ['redirect' => 'notes']],
        ]);

        $this->assertStringContainsString('<title>Members only</title>', $app->call('locked'));
    }

    #[Test]
    public function redirects_to_the_target_once_it_is_unlocked(): void
    {
        $app = $this->app(['request' => ['query' => ['redirect' => 'notes/hello']]]);
        Guard::grant($app->page('notes'));

        $response = $app->call('locked');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('https://example.com/notes/hello', $response->header('Location'));
    }

    #[Test]
    public function the_correct_password_grants_access_and_redirects_to_the_target(): void
    {
        $app = $this->submit(['password' => 's3cret']);

        $response = $app->call('locked', 'POST');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('https://example.com/notes/hello', $response->header('Location'));
        $this->assertFalse(Guard::isLocked($app->page('notes')));
    }

    /** @return array<string, array{0: string|array<int, string>}> */
    public static function invalidTargets(): array
    {
        return [
            'unknown page' => ['missing'],
            'array' => [['notes']],
        ];
    }

    #[Test]
    #[DataProvider('invalidTargets')]
    public function an_invalid_target_is_not_found(string|array $redirect): void
    {
        $app = $this->app(['request' => ['query' => ['redirect' => $redirect]]]);

        $this->assertFalse($app->call('locked'));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function rejectedSubmissions(): array
    {
        return [
            'wrong password' => [['password' => 'nope'], 'The password is incorrect'],
            'forged csrf' => [['password' => 's3cret', 'csrf' => 'forged'], 'The CSRF token is invalid'],
            'password array' => [['password' => ['s3cret']], 'The password is incorrect'],
            'csrf array' => [['password' => 's3cret', 'csrf' => [self::CSRF_TOKEN]], 'The CSRF token is invalid'],
        ];
    }

    #[Test]
    #[DataProvider('rejectedSubmissions')]
    public function renders_the_form_with_an_error_for_a_rejected_submission(array $body, string $error): void
    {
        $app = $this->submit($body);

        $html = $app->call('locked', 'POST');

        $this->assertStringContainsString($error, $html);
        $this->assertTrue(Guard::isLocked($app->page('notes')));
    }

    #[Test]
    public function the_logout_hook_revokes_every_grant(): void
    {
        $app = $this->app();
        Guard::grant($app->page('notes'));

        $app->trigger('locked-pages.logout');

        $this->assertTrue(Guard::isLocked($app->page('notes')));
    }
}
