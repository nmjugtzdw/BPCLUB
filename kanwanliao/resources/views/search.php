<?php
/** @var string $query */
/** @var array<int, array<string, mixed>> $threads */
/** @var array<int, array<string, mixed>> $titles */
/** @var App\Pager $pager */
/** @var bool $searched */
?>
<nav class="breadcrumb" aria-label="位置">
  <a href="/">首页</a>
</nav>

<section class="panel" aria-labelledby="search-title">
  <h1 id="search-title">搜索</h1>
  <form class="search-form" method="get" action="/search" role="search">
    <label class="visually-hidden" for="search-q">搜索作品和讨论</label>
    <input id="search-q" type="search" name="q" value="<?= App\View::e($query) ?>" maxlength="60"
           placeholder="搜作品名或讨论标题"<?= $searched ? '' : ' autofocus' ?>>
    <button type="submit">搜索</button>
  </form>
  <p class="muted search-hint">搜索只看标题和不含剧透的正文；标记为剧透的内容不会被搜到。</p>
</section>

<?php if (!$searched): ?>
  <section class="panel">
    <p class="empty-state">输入作品名或关键词开始搜索。</p>
  </section>
<?php else: ?>
  <section class="panel" aria-labelledby="search-titles-heading">
    <div class="toolbar">
      <h2 id="search-titles-heading">作品结果</h2>
      <span class="muted"><?= count($titles) ?> 部</span>
    </div>
    <?php if ($titles === []): ?>
      <p class="empty-state">没有匹配的作品。可以选择「未收录」相关讨论，或换个关键词。</p>
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
            </p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel" aria-labelledby="search-threads-heading">
    <div class="toolbar">
      <h2 id="search-threads-heading">讨论结果</h2>
      <span class="muted">共 <?= (int) $pager->total ?> 条</span>
    </div>
    <?php require __DIR__ . '/partials/thread_list.php'; ?>
    <?php $basePath = '/search'; $extraQuery = ['q' => $query]; require __DIR__ . '/partials/pager.php'; ?>
  </section>
<?php endif; ?>
