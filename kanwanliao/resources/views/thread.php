<?php
/**
 * @var array<string, mixed> $thread
 * @var string $categoryName
 * @var bool $isSpoiler
 * @var bool $revealed
 * @var bool $showBody
 * @var string $bodyText
 * @var string $notice
 * @var array<int, array<string, mixed>> $comments
 * @var int $commentTotal
 * @var array<string, mixed>|null $authUser
 * @var string $csrfToken
 */
$loggedIn = is_array($authUser);
$threadId = (int) $thread['id'];
?>
<nav class="breadcrumb" aria-label="位置">
  <a href="/">首页</a>
  <span aria-hidden="true">/</span>
  <a href="/category/<?= App\View::e((string) $thread['category']) ?>"><?= App\View::e($categoryName) ?></a>
</nav>

<article class="panel thread-detail">
  <h1><?= App\View::e((string) $thread['public_title']) ?></h1>
  <p class="thread-meta">
    <?= App\View::e((string) $thread['author_name']) ?>
    · <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $thread['created_at'])) ?>Z"><?= App\View::e(substr((string) $thread['created_at'], 0, 10)) ?></time>
    <?php if (!empty($thread['work_name'])): ?>
      · 作品 <a href="/title/<?= (int) $thread['title_id'] ?>">《<?= App\View::e((string) $thread['work_name']) ?>》</a>
    <?php endif; ?>
    <?php if ($isSpoiler): ?>
      · <span class="spoiler-flag">含剧透</span>
    <?php endif; ?>
  </p>

  <?php if ($isSpoiler && !$revealed): ?>
    <div class="spoiler-gate">
      <p class="spoiler-notice"><?= App\View::e($notice) ?></p>
      <p class="muted">下面这段正文会提到关键情节。想自己先看完再回来，就先别看。</p>
      <a class="button-link" href="/thread/<?= $threadId ?>?reveal=1#body">我确认，展开剧透</a>
    </div>
  <?php else: ?>
    <?php if ($isSpoiler && $revealed): ?>
      <p class="spoiler-open-note">已按你的选择展开剧透内容。<a href="/thread/<?= $threadId ?>">收起</a></p>
    <?php endif; ?>
    <?php if ($showBody): ?>
      <div class="post-body" id="body"><?= nl2br(App\View::e($bodyText)) ?></div>
    <?php else: ?>
      <p class="muted">这条讨论没有正文。</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($loggedIn): ?>
    <details class="report-box">
      <summary>举报这条讨论</summary>
      <form class="form" method="post" action="/report">
        <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
        <input type="hidden" name="target_type" value="thread">
        <input type="hidden" name="target_id" value="<?= $threadId ?>">
        <div class="field">
          <label for="report-thread-reason">举报原因</label>
          <textarea id="report-thread-reason" name="reason" rows="3" required></textarea>
        </div>
        <button class="btn-secondary" type="submit">提交举报</button>
      </form>
    </details>
  <?php endif; ?>
</article>

<section class="panel" id="comments" aria-labelledby="comments-title">
  <div class="toolbar">
    <h2 id="comments-title">评论</h2>
    <span class="muted">共 <?= (int) $commentTotal ?> 条</span>
  </div>

  <?php if ($comments === []): ?>
    <p class="empty-state">还没有评论。来说说你的看法。</p>
  <?php else: ?>
    <div class="comment-list">
      <?php foreach ($comments as $comment): ?>
        <article class="comment">
          <p class="thread-meta">
            <?= App\View::e((string) $comment['author_name']) ?>
            · <time datetime="<?= App\View::e(str_replace(' ', 'T', (string) $comment['created_at'])) ?>Z"><?= App\View::e(substr((string) $comment['created_at'], 0, 10)) ?></time>
            <?php if (!empty($comment['is_spoiler'])): ?>
              · <span class="spoiler-flag">含剧透</span>
            <?php endif; ?>
          </p>
          <?php if (!empty($comment['hidden'])): ?>
            <p class="spoiler-notice"><?= App\View::e((string) $comment['text']) ?></p>
          <?php else: ?>
            <p class="comment-body"><?= nl2br(App\View::e((string) $comment['text'])) ?></p>
          <?php endif; ?>

          <?php if ($loggedIn): ?>
            <details class="report-box">
              <summary>举报这条评论</summary>
              <form class="form" method="post" action="/report">
                <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
                <input type="hidden" name="target_type" value="comment">
                <input type="hidden" name="target_id" value="<?= (int) $comment['id'] ?>">
                <div class="field">
                  <label for="report-comment-<?= (int) $comment['id'] ?>">举报原因</label>
                  <textarea id="report-comment-<?= (int) $comment['id'] ?>" name="reason" rows="2" required></textarea>
                </div>
                <button class="btn-secondary" type="submit">提交举报</button>
              </form>
            </details>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($loggedIn): ?>
    <form class="form comment-form" method="post" action="/thread/<?= $threadId ?>/comment">
      <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
      <div class="field">
        <label for="comment-body">写评论</label>
        <textarea id="comment-body" name="body" rows="4" required maxlength="2000"></textarea>
      </div>
      <div class="field field-checkbox">
        <input type="checkbox" id="comment-spoiler" name="is_spoiler" value="1">
        <label for="comment-spoiler">这条评论包含剧透</label>
      </div>
      <button class="btn" type="submit">发表评论</button>
    </form>
  <?php else: ?>
    <p class="login-hint"><a href="/login">登录</a>后可以评论，没有账号可以先<a href="/register">注册</a>。</p>
  <?php endif; ?>
</section>
