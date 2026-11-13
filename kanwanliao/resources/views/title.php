<?php
/** @var array<string, mixed> $title */
/** @var array<int, array<string, mixed>> $threads */
$typeName = ($title['work_type'] ?? '') === 'movie' ? '电影' : '电视剧';
?>
<nav class="breadcrumb" aria-label="位置">
  <a href="/">首页</a>
  <span aria-hidden="true">/</span>
  <a href="/titles">作品</a>
</nav>

<section class="panel title-detail">
  <h1><?= App\View::e((string) $title['name']) ?></h1>
  <p class="thread-meta">
    <?= App\View::e($typeName) ?>
    <?php if (!empty($title['release_year'])): ?>
      · <?= (int) $title['release_year'] ?> 年
    <?php endif; ?>
    <?php if (!empty($title['source_label'])): ?>
      · 资料来源：<?= App\View::e((string) $title['source_label']) ?>
    <?php endif; ?>
  </p>
  <?php if (!empty($title['synopsis'])): ?>
    <p class="synopsis"><?= nl2br(App\View::e((string) $title['synopsis'])) ?></p>
  <?php endif; ?>
</section>

<section class="panel" aria-labelledby="title-threads-title">
  <div class="toolbar">
    <h2 id="title-threads-title">相关讨论</h2>
    <span class="muted">共 <?= count($threads) ?> 个话题</span>
  </div>
  <?php require __DIR__ . '/partials/thread_list.php'; ?>
</section>
