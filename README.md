<div align="center">
  <img src="./.github/favicon.svg" alt="Kirby Locked Pages logo" width="120">

# Kirby Locked Pages

Kirby Locked Pages is a plugin for [Kirby CMS](https://getkirby.com) that password-protects a page and its subpages behind a login form.

[Features](#features) •
[Options](#options) •
[Security Scope](#security-scope)

</div>

## When to Use

| I want to…                                      | Use                                     |
| ----------------------------------------------- | --------------------------------------- |
| Password-protect a page and its children        | `fields/locked-pages` blueprint group   |
| Lock a page on a multilingual site              | The toggle in the default language      |
| Give visitors a logout link                     | `locked-pages.logout` hook              |
| Restyle the password form                       | `site/templates/locked-pages-login.php` |
| Check lock state from my own code               | `Guard::isLocked($page)`                |
| Change the login slug, title, or error messages | `johannschopplich.locked-pages` options |

## Features

### 🔒 Password Protection

Toggle protection on any page through a globally registered blueprint field group. Add it to the `fields` of the page's blueprint:

```yml
fields:
  protection: fields/locked-pages
```

The group adds a `lockedPagesEnable` toggle and a `lockedPagesPassword` field. Visitors are redirected to a login form until they enter the correct password, and the grant is then stored in their session. Drafts and the error page are never locked, even when the fields are set.

Both fields are untranslatable: on a multilingual site, set them in the default language, and they apply to every translation.

### 🌳 Subtree Inheritance

Locking a page locks its whole subtree – you only add the field group at the subtree root. Unlocking the parent grants access to every descendant, keyed by the page's language-independent ID, so one unlock covers all translations. Changing the password locks the page again for everyone who unlocked it before. Content representations (`.json`, `.xml`, `.rss`, …) are locked alongside the page.

### 🚪 Logout Hook

Clear a visitor's grants by triggering the `locked-pages.logout` hook. Wire it to a route, then show a logout link only while the session holds a grant.

```php
// config.php
return [
    'routes' => [
        [
            'pattern' => 'logout',
            'action' => function () {
                kirby()->trigger('locked-pages.logout');
                go('/');
            }
        ]
    ]
];
```

```php
<?php use JohannSchopplich\LockedPages\Guard; ?>

<?php if (kirby()->session()->data()->get(Guard::SESSION_KEY)): ?>
  <a href="<?= url('logout') ?>">Logout</a>
<?php endif ?>
```

### 🌐 Translated Login Form

The login form speaks English, German, French, and Dutch, and follows the current language of a multilingual site. Other languages fall back to the default language, then to English. The `title` and `error` options replace the translated strings in every language.

### 🎨 Custom Login Template

The plugin ships a self-contained login template. Drop your own `locked-pages-login.php` into `site/templates/` and Kirby uses it automatically – the bundled [template](src/templates/locked-pages-login.php) is a good starting point. It receives `$error`: the message after a failed attempt, otherwise `false`.

## Requirements

- Kirby 5

## Installation

### Composer (Recommended)

```bash
composer require johannschopplich/kirby-locked-pages
```

### Manual Installation

Download a [release](https://github.com/johannschopplich/kirby-locked-pages/releases) and extract it to `/site/plugins/kirby-locked-pages`.

## Options

All options are namespaced under `johannschopplich.locked-pages`:

| Option           | Default              | Description                                                                                                                               |
| ---------------- | -------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `slug`           | `locked`             | Slug of the login form. On a multilingual site, it sits under the language prefix, e.g. `/de/locked`.                                     |
| `template`       | `locked-pages-login` | Template name for the login form.                                                                                                         |
| `title`          | –                    | Title of the login form. Replaces the translation, "Page locked" in English.                                                              |
| `longSession`    | `true`               | Keep grants in a long session: two weeks, no idle timeout. `false` keeps Kirby's normal session: two hours, ending after 30 idle minutes. |
| `error.password` | –                    | Message after a wrong password. Replaces the translation, "The password is incorrect" in English.                                         |
| `error.csrf`     | –                    | Message when the CSRF token fails. Replaces the translation, "The CSRF token is invalid" in English.                                      |

Example `config.php`:

```php
return [
    'johannschopplich.locked-pages' => [
        'slug' => 'geschuetzt',
        'title' => 'Geschützte Seite',
        'error' => [
            'password' => 'Das Passwort ist nicht korrekt',
            'csrf' => 'Der CSRF-Token ist nicht korrekt'
        ]
    ]
];
```

## Security Scope

This plugin gates page rendering – nothing else. Know its limits before relying on it:

- **Files are not protected.** Anything under `/media/pages/...` is served statically by the web server, and the URL hash is not a secret – a locked page's images and downloads stay reachable by direct URL. To gate files, serve them through your own route that checks `Guard::isLocked($page)` before streaming. Kirby's [files firewall cookbook](https://getkirby.com/docs/cookbook/security/files-firewall) covers the approach; adapt it to this plugin's session instead of a Panel login.
- **Passwords are stored in plaintext** in the page's content file, as an editor-visible shared secret. Treat a locked page as hidden from casual visitors, not as a vault.
- **Grants live as long as the session.** Kirby renews the session while the visitor keeps coming back, so with the default long session a grant ends one to two weeks after the last visit. Shorten it with `longSession => false`, or clear grants through the logout hook.
- **Saving a translation drops its own lock.** Kirby removes untranslatable fields from a translation when it saves one, so a page locked in a translation only becomes public once an editor saves that translation in the Panel. After upgrading from an earlier version, set the toggle and the password in the default language of every page locked in a translation.
- **The session is shared with the Panel.** Kirby keeps one session per browser, so with `longSession` on, unlocking a page also turns a Panel login in the same browser into a two-week session without idle timeout.

## Credits

Inspired by [kirby-securedpages](https://github.com/kerli81/kirby-securedpages).

## License

[MIT](./LICENSE) License © 2021-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
