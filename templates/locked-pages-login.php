<form method="post">
  <section>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" value="<?= esc(get('password', '')) ?>">

    <?php if ($error): ?>
      <p><?= $error ?></p>
    <?php endif ?>
  </section>

  <input type="hidden" name="csrf" value="<?= csrf() ?>">

  <section>
    <button type="submit">Open Page</button>
  </section>
</form>
