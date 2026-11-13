<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * 列表项也走剧透规则：$thread['excerpt'] 已由 Spoiler::summary() 生成，
 * 剧透帖在这里只有提示文本，没有任何正文。
 */
if ($threads === []):
?>
  <p class="empty-state">这里还没有讨论。等有人发出第一篇，就会出现在这里。</p>
<?php else: ?>
  <div class="thread-list">
    <?php foreach ($threads as $thread): ?>
      <article class="thread">
        <a class="tag" href="/category/<?= App\View::e((string) $thread['category']) ?>"><?= App\View::e((string) $thread['category_name']) ?></a>
        <div class="thread-body">
          <h3><a class="thread-link" href="/thread/<?= (int) $thread['id'] ?>"><?= App\View::e((string) $thread['title']) ?></a></h3>
          <p class="thread-meta">
            <?php if (!empty($thread['work_name'])): ?>
              <a href="/title/<?= (int) $thread['title_id'] ?>">《<?= App\View::e((string) $thread['work_name']) ?>》</a>
            <?php else: ?>
              未关联作品
            <?php endif; ?>
            <?php if (!empty($thread['author_name'])): ?>
              · <?= App\View::e((string) $thread['author_name']) ?>
            <?php endif; ?>
            · <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $thread['created_at'])) ?>Z"><?= App\View::e(substr((string) $thread['created_at'], 0, 10)) ?></time>
            <?php if (!empty($thread['is_spoiler'])): ?>
              · <span class="spoiler-flag">含剧透</span>
            <?php endif; ?>
          </p>
          <?php if (!empty($thread['is_spoiler'])): ?>
            <p class="spoiler-notice"><?= App\View::e((string) $thread['excerpt']) ?></p>
          <?php else: ?>
            <p class="excerpt"><?= App\View::e((string) $thread['excerpt']) ?></p>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
