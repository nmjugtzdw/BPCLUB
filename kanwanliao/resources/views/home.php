<?php
/** @var array<string, mixed> $thread */
/** @var array<int, array<string, mixed>> $threads */
/** @var App\Pager $pager */
/** @var string $basePath */
/** @var array<string, string> $categories */
/** @var string|null $activeCategory */
/** @var string $heading */
/** @var string $subheading */

$heading = $heading ?? '最近大家在聊';
$subheading = $subheading ?? '不必是专业影评，一句真实感受也值得分享。';
?>
<section class="intro">
  <div>
    <p class="eyebrow">看完一部，聊聊感受</p>
    <h1><?= App\View::e($heading) ?></h1>
    <p class="muted"><?= App\View::e($subheading) ?></p>
  </div>
  <span class="coming" aria-disabled="true">发布功能筹备中</span>
</section>

<section class="panel" aria-labelledby="list-title">
  <div class="toolbar">
    <h2 id="list-title">讨论列表</h2>
    <span class="muted">共 <?= (int) $pager->total ?> 个话题</span>
  </div>

  <nav class="category-list" aria-label="话题分类">
    <a class="category-chip<?= $activeCategory === null ? ' is-active' : '' ?>" href="/"<?= $activeCategory === null ? ' aria-current="page"' : '' ?>>全部</a>
    <?php foreach ($categories as $slug => $name): ?>
      <a class="category-chip<?= $activeCategory === $slug ? ' is-active' : '' ?>"
         href="/category/<?= App\View::e($slug) ?>"<?= $activeCategory === $slug ? ' aria-current="page"' : '' ?>><?= App\View::e($name) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php require __DIR__ . '/partials/thread_list.php'; ?>

  <?php $extraQuery = []; require __DIR__ . '/partials/pager.php'; ?>
</section>
