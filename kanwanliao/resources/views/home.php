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
$featuredWorks = [];
if ($activeCategory === null && $pager->page === 1) {
    // Only works attached to already-public recent threads; never invent a popularity ranking.
    foreach ($threads as $thread) {
        $id = (int) ($thread['title_id'] ?? 0);
        $name = (string) ($thread['work_name'] ?? '');
        if ($id < 1 || trim($name) === '' || isset($featuredWorks[$id])) {
            continue;
        }
        $featuredWorks[$id] = ['id' => $id, 'name' => $name, 'type' => (string) ($thread['work_type'] ?? '')];
        if (count($featuredWorks) === 4) { break; }
    }
}
?>
<?php if ($featuredWorks !== []): ?>
<section class="featured-works" aria-labelledby="featured-works-title">
  <div class="featured-head">
    <div><p class="featured-kicker">IN DISCUSSION / 近期讨论作品</p><h2 id="featured-works-title">从一部作品开始</h2></div>
    <a href="/titles">全部作品 <span aria-hidden="true">↗</span></a>
  </div>
  <p class="featured-note">以下来自近期公开讨论；AI 设计配图，非官方海报。</p>
  <div class="featured-scroll" role="region" aria-label="近期讨论作品，可横向滚动" tabindex="0">
    <div class="featured-grid">
      <?php $coverIndex = 0; foreach ($featuredWorks as $work): $coverIndex++; ?>
        <a class="featured-work" href="/title/<?= (int) $work['id'] ?>">
          <div class="featured-cover cover-<?= (($work['id'] - 1) % 4) + 1 ?>" aria-hidden="true"><span>看完聊 / <?= str_pad((string) $coverIndex, 2, '0', STR_PAD_LEFT) ?></span><span>AFTER<br>THE CREDITS</span></div>
          <span class="featured-name"><?= App\View::e($work['name']) ?></span>
          <span class="featured-type"><?= $work['type'] === 'movie' ? '电影' : ($work['type'] === 'series' ? '电视剧' : '作品') ?> <span aria-hidden="true">↗</span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="panel discussion-panel" aria-labelledby="list-title">
  <div class="toolbar">
    <h2 id="list-title"><?= $activeCategory === null ? '最近讨论' : App\View::e($heading) . '讨论' ?></h2>
    <span class="muted">共 <?= (int) $pager->total ?> 个话题</span>
  </div>

  <?php require __DIR__ . '/partials/thread_list.php'; ?>

  <?php $extraQuery = []; require __DIR__ . '/partials/pager.php'; ?>
</section>
