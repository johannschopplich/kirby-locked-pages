<?php

/**
 * Minimal, self-contained login page. Override it by placing your own
 * `site/templates/locked-pages-login.php` in your project.
 *
 * @var \Kirby\Cms\Page $page
 * @var string|false $error
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($kirby->language()?->code() ?? 'en', 'attr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= $page->title()->esc() ?></title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f4f4f5; color: #18181b; }
    .locked-pages { width: 100%; max-width: 20rem; padding: 2rem; box-sizing: border-box; }
    .locked-pages h1 { margin: 0 0 1.5rem; font-size: 1.25rem; }
    .locked-pages label { display: block; margin-bottom: .5rem; font-size: .875rem; }
    .locked-pages input { width: 100%; padding: .5rem .625rem; margin-bottom: 1rem; box-sizing: border-box; border: 1px solid #d4d4d8; border-radius: .375rem; font: inherit; }
    .locked-pages button { width: 100%; padding: .5rem; border: 0; border-radius: .375rem; background: #18181b; color: #fff; font: inherit; cursor: pointer; }
    .locked-pages .error { margin: 0 0 1rem; color: #dc2626; font-size: .875rem; }
  </style>
</head>
<body>
  <form class="locked-pages" method="post">
    <h1><?= $page->title()->esc() ?></h1>

    <?php if ($error): ?>
      <p class="error"><?= esc($error) ?></p>
    <?php endif ?>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" value="<?= esc(get('password', '')) ?>" autofocus>

    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <button type="submit">Open page</button>
  </form>
</body>
</html>
