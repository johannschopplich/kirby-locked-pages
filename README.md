<div align="center">
  <img src="./.github/favicon.svg" alt="Kirby Locked Pages logo" width="120">

# Kirby Locked Pages

Password-protect a page – and its entire subtree – behind a login form, for the Kirby CMS.

[Features](#features) •
[Options](#options) •
[Security Scope](#security-scope)

</div>

## When to Use

| I want to…                                       | Use                                     |
| ------------------------------------------------ | --------------------------------------- |
| Password-protect a page and its children         | `fields/locked-pages` blueprint group   |
| Give visitors a logout link                      | `locked-pages.logout` hook              |
| Restyle the password form                        | `site/templates/locked-pages-login.php` |
| Check lock state from my own code                | `Guard::isLocked($page)`                |
| Change the login slug, title, or error messages  | `johannschopplich.locked-pages` options |

## Features

### 🔒 Password Protection

Toggle protection on any page through a globally registered blueprint field group. Visitors are redirected to a login form until they enter the correct password, and the grant is then stored in their session.

```yml
sections:
  access:
    type: fields
    fields:
      protection: fields/locked-pages
```

The group adds a `lockedPagesEnable` toggle and a `lockedPagesPassword` field. Drafts and the error page are never locked, even when the fields are set.

### 🌳 Subtree Inheritance

Locking a page locks its whole subtree – you only add the field group at the subtree root. Unlocking the parent grants access to every descendant, keyed by the page's language-independent ID, so one unlock covers all translations. Content representations (`.json`, `.xml`, `.rss`, …) are locked alongside the page.

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

### 🎨 Custom Login Template

The plugin ships a self-contained login template. Drop your own `locked-pages-login.php` into `site/templates/` and Kirby uses it automatically – the bundled [template](templates/locked-pages-login.php) is a good starting point.

## Requirements

- Kirby 5
- PHP 8.3+

## Installation

### Composer (Recommended)

```bash
composer require johannschopplich/kirby-locked-pages
```

### Manual Installation

Download and copy this repository to `/site/plugins/kirby-locked-pages`.

## Options

All options are namespaced under `johannschopplich.locked-pages`:

| Option           | Default                     | Description                                                                            |
| ---------------- | --------------------------- | ------------------------------------------------------------------------------------- |
| `slug`           | `locked`                    | Slug of the login form, relative to the site URL.                                     |
| `template`       | `locked-pages-login`        | Template name for the login form.                                                     |
| `title`          | `Page locked`               | Title rendered on the login form.                                                     |
| `longSession`    | `true`                      | Keep grants in a long (2-week) session with no idle timeout. `false` for a 2-hour one. |
| `error.password` | `The password is incorrect` | Message shown after a wrong password.                                                 |
| `error.csrf`     | `The CSRF token is invalid` | Message shown when the CSRF token fails.                                              |

The `error` messages are nested under an `error` key. Example `config.php`:

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
- **Grants last up to two weeks** by default. Shorten them with `longSession => false`, or clear them through the logout hook.

## Credits

Inspired by [kirby-securedpages](https://github.com/kerli81/kirby-securedpages).

## License

[MIT](./LICENSE) License © 2021-PRESENT [Johann Schopplich](https://github.com/johannschopplich)
