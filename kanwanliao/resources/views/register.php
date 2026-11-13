<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string $csrfToken
 */
$username = (string) ($old['username'] ?? '');
$email = (string) ($old['email'] ?? '');
?>
<section class="panel auth-panel">
  <h1>注册</h1>
  <p class="muted">用户名 2–20 个字，可以用中文、字母、数字和下划线；密码至少 8 位，需同时包含字母和数字。</p>

  <?php if (($errors['form'] ?? '') !== ''): ?>
    <div class="alert alert-error" role="alert"><?= App\View::e($errors['form']) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="/register">
    <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">

    <div class="field">
      <label for="username">用户名</label>
      <input
        id="username" name="username" type="text" value="<?= App\View::e($username) ?>"
        autocomplete="username" required maxlength="20"
        <?= isset($errors['username']) ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>>
      <?php if (isset($errors['username'])): ?>
        <p class="field-error" id="username-error"><?= App\View::e($errors['username']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="email">邮箱</label>
      <input
        id="email" name="email" type="email" value="<?= App\View::e($email) ?>"
        autocomplete="email" required maxlength="191"
        <?= isset($errors['email']) ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
      <?php if (isset($errors['email'])): ?>
        <p class="field-error" id="email-error"><?= App\View::e($errors['email']) ?></p>
      <?php else: ?>
        <p class="field-hint">仅用于账号识别，不会公开显示。</p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="password">密码</label>
      <input
        id="password" name="password" type="password" autocomplete="new-password" required
        <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
      <?php if (isset($errors['password'])): ?>
        <p class="field-error" id="password-error"><?= App\View::e($errors['password']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="password_confirm">再输一次密码</label>
      <input
        id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required
        <?= isset($errors['password_confirm']) ? 'aria-invalid="true" aria-describedby="password-confirm-error"' : '' ?>>
      <?php if (isset($errors['password_confirm'])): ?>
        <p class="field-error" id="password-confirm-error"><?= App\View::e($errors['password_confirm']) ?></p>
      <?php endif; ?>
    </div>

    <button class="btn" type="submit">注册并登录</button>
  </form>

  <p class="muted">已经有账号？<a href="/login">直接登录</a>。</p>
</section>
