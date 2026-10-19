<?php

declare(strict_types = 1);

use JohannSchopplich\LockedPages\Guard;
use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
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

    #[Test]
    public function a_wrong_password_renders_the_form_with_an_error(): void
    {
        $app = $this->submit(['password' => 'nope']);

        $html = $app->call('locked', 'POST');

        $this->assertStringContainsString('The password is incorrect', $html);
        $this->assertTrue(Guard::isLocked($app->page('notes')));
    }

    #[Test]
    public function an_invalid_csrf_token_renders_the_form_with_an_error(): void
    {
        $app = $this->submit(['password' => 's3cret', 'csrf' => 'forged']);

        $html = $app->call('locked', 'POST');

        $this->assertStringContainsString('The CSRF token is invalid', $html);
        $this->assertTrue(Guard::isLocked($app->page('notes')));
    }

    #[Test]
    public function an_unknown_target_is_not_found(): void
    {
        $app = $this->app(['request' => ['query' => ['redirect' => 'missing']]]);

        $this->assertFalse($app->call('locked'));
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
