<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string $csrfToken
 */
$identity = (string) ($old['identity'] ?? '');
?>
<section class="panel auth-panel">
  <h1>登录</h1>
  <p class="muted">用注册时的用户名或邮箱登录。</p>

  <?php if (($errors['form'] ?? '') !== ''): ?>
    <div class="alert alert-error" role="alert"><?= App\View::e($errors['form']) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="/login">
    <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">

    <div class="field">
      <label for="identity">用户名或邮箱</label>
      <input
        id="identity" name="identity" type="text" value="<?= App\View::e($identity) ?>"
        autocomplete="username" required
        <?= isset($errors['identity']) ? 'aria-invalid="true" aria-describedby="identity-error"' : '' ?>>
      <?php if (isset($errors['identity'])): ?>
        <p class="field-error" id="identity-error"><?= App\View::e($errors['identity']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="password">密码</label>
      <input
        id="password" name="password" type="password" autocomplete="current-password" required
        <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
      <?php if (isset($errors['password'])): ?>
        <p class="field-error" id="password-error"><?= App\View::e($errors['password']) ?></p>
      <?php endif; ?>
    </div>

    <button class="btn" type="submit">登录</button>
  </form>

  <p class="muted">还没有账号？<a href="/register">注册一个</a>。</p>
</section>
