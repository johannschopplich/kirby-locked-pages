# Kirby Locked Pages

Protect pages that you want to hide from unwanted views with a password.

## Key features

- 🔒 Easily password-protect single pages
- 🖼 Panel blueprints included

## Requirements

- Kirby 3
- PHP 7.3+

## Installation

### Download

Download and copy this repository to `/site/plugins/kirby-locked-pages`.

### Git submodule

```
git submodule add https://github.com/johannschopplich/kirby-locked-pages.git site/plugins/kirby-locked-pages
```

### Composer

```
composer require johannschopplich/kirby-locked-pages
```

## Usage

When a locked page is visited, Kirby will redirect to a login form ([virtual page](https://getkirby.com/docs/guide/virtual-pages)), where a password may be entered. Once the user enters the correct password, he will be redirected back to the page url which was locked before. The session also records that this page is now unlocked for the user. After the session expires, the user has to enter a password again.

### Configuration

See the list of [available options below](#options).

### Blueprints

Add the protection field group to a page blueprint which shall be lockable by password:

```yml
sections:
  access:
    type: fields
    fields:
      security: fields/locked-pages
```

The field group `fields/locked-pages` is registered globally by the plugin.

### Templates

You probably want to customize the template which will show the password form. The [template provided](templates/locked-pages-login.php) is suited to be used as-is, but you are welcome to create a `locked-pages-login.php` template inside your `site/templates` folder. The plugin's included template may be used as a starting point.

Once you've defined a custom template, Kirby will automatically use the one you've created rather than the one included by the plugin.

## Options

> All options are namespaced under `kirby-extended.locked-pages`.

| Option | Default | Description |
| --- | --- | --- |
| `slug` | `locked` | Slug for login form (absolute to the site url). |
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
]
```

## Credits

- Inspired by [kirby-securedpages](https://github.com/kerli81/kirby-securedpages)

## License

[MIT](https://opensource.org/licenses/MIT)
