<?php

use Kirby\Toolkit\Str;

/**
 * Minimal, self-contained login page. Override it by placing your own
 * `site/templates/locked-pages-login.php` in your project.
 *
 * @var \Kirby\Cms\App $kirby
 * @var \Kirby\Cms\Page $page
 * @var string|false $error
 */
?>
<!DOCTYPE html>
<html lang="<?= Str::esc($kirby->language()?->code() ?? 'en', 'attr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">

  <title><?= $page->title()->esc() ?></title>

  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
      padding: 10%;
      line-height: 1.5em;
    }
    form {
      max-width: 20em;
      margin: 0 auto;
    }
    h1 {
      margin: 0 0 1.5rem;
      font-size: 1.5em;
    }
    label {
      display: block;
      margin-bottom: .5rem;
    }
    input {
      width: 100%;
      padding: .5rem;
      margin-bottom: 1.5rem;
      box-sizing: border-box;
      border: 1px solid #ccc;
      border-radius: .25rem;
      font: inherit;
    }
    button {
      width: 100%;
      padding: .5rem;
      border: 0;
      border-radius: .25rem;
      background: #000;
      color: #fff;
      font: inherit;
      cursor: pointer;
    }
    .error {
      margin: 0 0 1.5rem;
      color: #cc0000;
    }
    a {
      color: inherit;
    }
  </style>

</head>
<body>

  <form method="post">
    <h1><?= $page->title()->esc() ?></h1>

    <?php if ($error): ?>
      <p class="error"><?= Str::esc($error) ?></p>
    <?php endif ?>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autofocus>

    <input type="hidden" name="csrf" value="<?= $kirby->csrf() ?>">

    <button type="submit">Open page</button>
  </form>

</body>
</html>
