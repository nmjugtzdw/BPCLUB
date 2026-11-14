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
    <?php $rowNumber = 0; foreach ($threads as $thread): $rowNumber++; ?>
      <article class="thread">
        <div class="thread-index"><span aria-hidden="true"><?= str_pad((string) $rowNumber, 2, '0', STR_PAD_LEFT) ?></span><a class="thread-category" href="/category/<?= App\View::e((string) $thread['category']) ?>"><?= App\View::e((string) $thread['category_name']) ?></a></div>
        <div class="thread-body">
          <?php if (!empty($thread['work_name']) && (int) ($thread['title_id'] ?? 0) > 0): ?>
            <div class="thread-art"><div class="thread-art-image cover-<?= (((int) $thread['title_id'] - 1) % 4) + 1 ?>" aria-hidden="true"></div><span>AI 配图</span></div>
          <?php endif; ?>
          <div class="thread-heading">
            <h3><a class="thread-link" href="/thread/<?= (int) $thread['id'] ?>"><?= App\View::e((string) $thread['title']) ?></a></h3>
            <?php if (!empty($thread['is_spoiler'])): ?>
              <span class="spoiler-notice spoiler-seal"><span>剧情封签</span> · 含剧透<span class="visually-hidden"><?= App\View::e((string) $thread['excerpt']) ?></span></span>
            <?php endif; ?>
          </div>
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
          </p>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
