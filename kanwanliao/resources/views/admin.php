<?php
/**
 * @var array<int, array<string, mixed>> $reports
 * @var array<int, array<string, mixed>> $pendingThreads
 * @var array<int, array<string, mixed>> $pendingComments
 * @var array<int, array<string, mixed>> $audit
 * @var string $csrfToken
 */
/**
 * 举报对象的「查看内容」地址：帖子直接用 id，评论必须回到它所属的帖子，
 * 否则会把评论 id 当成帖子 id，跳到一个不相干的帖子或 404。
 */
$reportLink = static function (array $report): string {
    if ((string) $report['target_type'] === 'thread') {
        return '/thread/' . (int) $report['target_id'];
    }
    $threadId = (int) ($report['comment_thread_id'] ?? 0);
    return $threadId > 0 ? '/thread/' . $threadId . '#comments' : '/';
};
?>
<section class="panel">
  <h1>管理后台</h1>
  <p class="muted">处理举报与待审内容。每个操作都会记入操作记录，包含操作人、动作和对象。</p>
</section>

<section class="panel" aria-labelledby="reports-title">
  <div class="toolbar">
    <h2 id="reports-title">举报队列</h2>
    <span class="muted">共 <?= count($reports) ?> 条待处理</span>
  </div>

  <?php if ($reports === []): ?>
    <p class="empty-state">没有待处理的举报。</p>
  <?php else: ?>
    <div class="admin-list">
      <?php foreach ($reports as $report): ?>
        <?php
        $type = (string) $report['target_type'];
        $targetId = (int) $report['target_id'];
        ?>
        <article class="admin-item">
          <p class="thread-meta">
            举报 #<?= (int) $report['id'] ?> ·
            <?= App\View::e((string) $report['reporter_name']) ?> ·
            <?= $type === 'thread' ? '帖子' : '评论' ?> #<?= $targetId ?> ·
            <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $report['created_at'])) ?>Z"><?= App\View::e(substr((string) $report['created_at'], 0, 10)) ?></time>
          </p>
          <p class="admin-reason"><?= nl2br(App\View::e((string) $report['reason'])) ?></p>
          <p class="admin-actions">
            <a class="btn-secondary" href="<?= App\View::e($reportLink($report)) ?>">查看内容</a>
            <form class="inline-form" method="post" action="/admin/report/<?= (int) $report['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="action" value="dismiss">
              <button class="btn-secondary" type="submit">驳回举报</button>
            </form>
            <form class="inline-form" method="post" action="/admin/report/<?= (int) $report['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="action" value="hide_target">
              <button class="btn" type="submit">隐藏被举报内容</button>
            </form>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="panel" aria-labelledby="pending-threads-title">
  <div class="toolbar">
    <h2 id="pending-threads-title">待审帖子</h2>
    <span class="muted">共 <?= count($pendingThreads) ?> 条</span>
  </div>

  <?php if ($pendingThreads === []): ?>
    <p class="empty-state">没有待审的帖子。</p>
  <?php else: ?>
    <div class="admin-list">
      <?php foreach ($pendingThreads as $thread): ?>
        <article class="admin-item">
          <p class="thread-meta">
            #<?= (int) $thread['id'] ?> · <?= App\View::e((string) $thread['author_name']) ?>
            · <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $thread['created_at'])) ?>Z"><?= App\View::e(substr((string) $thread['created_at'], 0, 10)) ?></time>
            <?php if ((int) $thread['is_spoiler'] === 1): ?>· <span class="spoiler-flag">含剧透</span><?php endif; ?>
          </p>
          <p class="admin-reason"><strong><?= App\View::e((string) $thread['title']) ?></strong></p>
          <p class="admin-reason"><?= nl2br(App\View::e(mb_substr((string) $thread['body'], 0, 400))) ?></p>
          <p class="admin-actions">
            <form class="inline-form" method="post" action="/admin/thread/<?= (int) $thread['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="status" value="visible">
              <button class="btn" type="submit">通过并公开</button>
            </form>
            <form class="inline-form" method="post" action="/admin/thread/<?= (int) $thread['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="status" value="hidden">
              <button class="btn-secondary" type="submit">不公开</button>
            </form>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="panel" aria-labelledby="pending-comments-title">
  <div class="toolbar">
    <h2 id="pending-comments-title">待审评论</h2>
    <span class="muted">共 <?= count($pendingComments) ?> 条</span>
  </div>

  <?php if ($pendingComments === []): ?>
    <p class="empty-state">没有待审的评论。</p>
  <?php else: ?>
    <div class="admin-list">
      <?php foreach ($pendingComments as $comment): ?>
        <article class="admin-item">
          <p class="thread-meta">
            #<?= (int) $comment['id'] ?> · <?= App\View::e((string) $comment['author_name']) ?>
            · 在 <a href="/thread/<?= (int) $comment['thread_id'] ?>"><?= App\View::e(mb_substr((string) $comment['thread_title'], 0, 40)) ?></a>
            · <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $comment['created_at'])) ?>Z"><?= App\View::e(substr((string) $comment['created_at'], 0, 10)) ?></time>
          </p>
          <p class="admin-reason"><?= nl2br(App\View::e(mb_substr((string) $comment['body'], 0, 400))) ?></p>
          <p class="admin-actions">
            <form class="inline-form" method="post" action="/admin/comment/<?= (int) $comment['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="status" value="visible">
              <button class="btn" type="submit">通过并公开</button>
            </form>
            <form class="inline-form" method="post" action="/admin/comment/<?= (int) $comment['id'] ?>">
              <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
              <input type="hidden" name="status" value="hidden">
              <button class="btn-secondary" type="submit">不公开</button>
            </form>
          </p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="panel" aria-labelledby="audit-title">
  <div class="toolbar">
    <h2 id="audit-title">操作记录</h2>
    <span class="muted">最近 <?= count($audit) ?> 条</span>
  </div>

  <?php if ($audit === []): ?>
    <p class="empty-state">还没有管理操作记录。</p>
  <?php else: ?>
    <table class="admin-table">
      <caption class="visually-hidden">最近的管理操作记录</caption>
      <thead>
        <tr>
          <th scope="col">时间</th>
          <th scope="col">操作人</th>
          <th scope="col">动作</th>
          <th scope="col">对象</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($audit as $entry): ?>
          <tr>
            <td><?= App\View::e(str_replace('T', ' ', substr((string) $entry['created_at'], 0, 16))) ?></td>
            <td><?= App\View::e((string) $entry['actor_name']) ?></td>
            <td><?= App\View::e((string) $entry['action']) ?></td>
            <td><?= App\View::e((string) $entry['target_type']) ?><?= $entry['target_id'] === null ? '' : ' #' . (int) $entry['target_id'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
