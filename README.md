# Kirby Locked Pages

Protect pages that you want to hide from unwanted views with a password.

## Key Features

- 🔒 Easily password-protect single pages
- 🪝 Custom logout hook
- 🖼 Panel blueprints included

## Requirements

- Kirby 3
- PHP 7.4+

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby-locked-pages`.

### Git Submodule

```
git submodule add https://github.com/johannschopplich/kirby-locked-pages.git site/plugins/kirby-locked-pages
```

### Composer

```
composer require johannschopplich/kirby-locked-pages
```

## Usage

When a locked page is visited, Kirby will redirect to a login form ([virtual page](https://getkirby.com/docs/guide/virtual-pages)), where a password may be entered. Once the user enters the correct password, he will be redirected back to the page URL which was locked before.

The user session also records that this page is now unlocked for further requests. After the session expires, the user has to enter a password again.

### Configuration

See the list of [available options below](#options).

### Blueprints

Add the protection field group to a page blueprint, which shall be lockable by password:

```yml
sections:
  access:
    type: fields
    fields:
      security: fields/locked-pages
```

The field group `fields/locked-pages` is registered globally by the plugin.

> ℹ️ Note: The error page is not lockable. Although it is possible to add the fields, they will have no effect.

### Templates

You probably want to customize the template which will show the password form. The [template provided](templates/locked-pages-login.php) is suited to be used as-is, but you are welcome to create a `locked-pages-login.php` template inside your `site/templates` folder. The plugin's included template may be used as a starting point.

Once you've defined a custom template, Kirby will automatically use the one you've created rather than the one included by the plugin.

### Logout Hook

It is often helpful and good UX to provide the user a way of logging out. You can use a custom [Kirby hook](https://getkirby.com/docs/reference/plugins/extensions/hooks) for this use-case.

Trigger the `locked-pages.logout` hook to clear the user's plugin session data. Once logged out, he will have to enter the password again.

```php
kirby()->trigger('locked-pages.logout');
```

## Options

> All options are namespaced under `kirby-extended.locked-pages`.

| Option | Default | Description |
| --- | --- | --- |
| `slug` | `locked` | Slug for login form (absolute to the site URL). |
| `template` | `locked-pages-login` | Optional name of custom template (has to be created manually). |
| `title` | `Page locked` | Title of the login form. |
| `error.csrf` | `The CSRF token is invalid` | Error message for invalid CSRF. |
| `error.password` | `The password is incorrect` | Error message for invalid password. |

> All of the `error` options have to be wrapped in an array.

To give an example for your `config.php`:

```php
return [
    'kirby-extended.locked-pages' => [
        'slug' => 'geschuetzt',
        'title' => 'Geschützte Seite',
        'error' => [
            'csrf' => 'Der CSRF-Token ist nicht korrket',
            'password' => 'Das Passwort ist nicht korrekt'
        ]
    ]
];
```

## Credits

- Inspired by [kirby-securedpages](https://github.com/kerli81/kirby-securedpages)

## License

[MIT](./LICENSE) License © 2021 [Johann Schopplich](https://github.com/johannschopplich)
