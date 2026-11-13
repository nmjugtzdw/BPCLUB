<?php
/**
 * @var array<string, mixed> $profile
 * @var int $threadTotal
 * @var array<int, array<string, mixed>> $threads
 * @var App\Pager $pager
 */
$joined = substr((string) ($profile['created_at'] ?? ''), 0, 10);
?>
<section class="panel profile-head">
  <h1><?= App\View::e((string) $profile['username']) ?></h1>
  <p class="thread-meta">
    公开讨论 <strong><?= (int) $threadTotal ?></strong> 条
    <?php if ($joined !== ''): ?>
      · 加入于 <time datetime="<?= App\View::e($joined) ?>"><?= App\View::e($joined) ?></time>
    <?php endif; ?>
  </p>
</section>

<section class="panel" aria-labelledby="user-threads-title">
  <div class="toolbar">
    <h2 id="user-threads-title">TA 的讨论</h2>
    <span class="muted">共 <?= (int) $threadTotal ?> 条</span>
  </div>
  <?php require __DIR__ . '/partials/thread_list.php'; ?>
  <?php
  $basePath = '/user/' . (int) $profile['id'];
  $extraQuery = [];
  require __DIR__ . '/partials/pager.php';
  ?>
</section>
