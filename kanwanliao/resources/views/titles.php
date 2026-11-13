<?php
/** @var array<int, array<string, mixed>> $titles */
/** @var App\Pager $pager */
/** @var string $query */
?>
<nav class="breadcrumb" aria-label="位置">
  <a href="/">首页</a>
</nav>

<section class="intro">
  <div>
    <p class="eyebrow">按作品找讨论</p>
    <h1>作品</h1>
    <p class="muted">同名作品按年份和类型分开列出，不会混在一起。</p>
  </div>
</section>

<section class="panel" aria-labelledby="titles-title">
  <div class="toolbar">
    <h2 id="titles-title">全部作品</h2>
    <span class="muted">共 <?= (int) $pager->total ?> 部</span>
  </div>

  <form class="search-form" method="get" action="/titles" role="search">
    <label class="visually-hidden" for="title-q">按名称筛选作品</label>
    <input id="title-q" type="search" name="q" value="<?= App\View::e($query) ?>" maxlength="60" placeholder="输入作品名称">
    <button type="submit">筛选</button>
    <?php if ($query !== ''): ?>
      <a class="clear-link" href="/titles">清除</a>
    <?php endif; ?>
  </form>

  <?php if ($titles === []): ?>
    <p class="empty-state"><?= $query === '' ? '还没有收录作品。' : '没有匹配「' . App\View::e($query) . '」的作品。' ?></p>
  <?php else: ?>
    <div class="work-list">
      <?php foreach ($titles as $work): ?>
        <article class="work-item">
          <h3><a href="/title/<?= (int) $work['id'] ?>"><?= App\View::e((string) $work['name']) ?></a></h3>
          <p class="thread-meta">
            <?= ($work['work_type'] ?? '') === 'movie' ? '电影' : '电视剧' ?>
            <?php if (!empty($work['release_year'])): ?>
              · <?= (int) $work['release_year'] ?> 年
            <?php endif; ?>
            · <a href="/title/<?= (int) $work['id'] ?>"><?= (int) ($work['thread_count'] ?? 0) ?> 个讨论</a>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php $basePath = '/titles'; $extraQuery = $query === '' ? [] : ['q' => $query]; require __DIR__ . '/partials/pager.php'; ?>
</section>
