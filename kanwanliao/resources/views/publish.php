<?php
/**
 * @var array<string, string> $categories
 * @var array<int, array<string, mixed>> $workOptions
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string $csrfToken
 */
$selectedCategory = (string) ($old['category'] ?? '');
$titleValue = (string) ($old['title'] ?? '');
$bodyValue = (string) ($old['body'] ?? '');
$selectedWork = (string) ($old['title_id'] ?? '');
$spoilerChecked = (string) ($old['is_spoiler'] ?? '') === '1';
?>
<section class="panel">
  <h1>发布讨论</h1>
  <p class="muted">聊聊刚看完的感受、剧情细节或者想看的类型。标了剧透的内容，列表和搜索里都不会露出正文。</p>

  <?php if (($errors['form'] ?? '') !== ''): ?>
    <div class="alert alert-error" role="alert"><?= App\View::e($errors['form']) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="/publish">
    <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">

    <div class="field">
      <label for="category">分类</label>
      <select id="category" name="category" required
        <?= isset($errors['category']) ? 'aria-invalid="true" aria-describedby="category-error"' : '' ?>>
        <option value="">请选择</option>
        <?php foreach ($categories as $slug => $label): ?>
          <option value="<?= App\View::e((string) $slug) ?>"<?= $selectedCategory === $slug ? ' selected' : '' ?>><?= App\View::e((string) $label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['category'])): ?>
        <p class="field-error" id="category-error"><?= App\View::e($errors['category']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="title">标题</label>
      <input
        id="title" name="title" type="text" value="<?= App\View::e($titleValue) ?>" required maxlength="150"
        <?= isset($errors['title']) ? 'aria-invalid="true" aria-describedby="title-error"' : '' ?>>
      <?php if (isset($errors['title'])): ?>
        <p class="field-error" id="title-error"><?= App\View::e($errors['title']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="title_id">关联作品（可选）</label>
      <select id="title_id" name="title_id"
        <?= isset($errors['title_id']) ? 'aria-invalid="true" aria-describedby="work-error"' : '' ?>>
        <option value="">不关联</option>
        <?php foreach ($workOptions as $work): ?>
          <option value="<?= (int) $work['id'] ?>"<?= $selectedWork === (string) $work['id'] ? ' selected' : '' ?>>
            <?= App\View::e((string) $work['name']) ?><?php
              $year = isset($work['release_year']) && $work['release_year'] !== null ? (string) $work['release_year'] : '';
              $type = (string) ($work['work_type'] ?? '') === 'series' ? '剧集' : '电影';
              echo App\View::e('（' . $type . ($year === '' ? '' : ' · ' . $year) . '）');
            ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['title_id'])): ?>
        <p class="field-error" id="work-error"><?= App\View::e($errors['title_id']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="body">正文</label>
      <textarea id="body" name="body" rows="10" required
        <?= isset($errors['body']) ? 'aria-invalid="true" aria-describedby="body-error"' : '' ?>><?= App\View::e($bodyValue) ?></textarea>
      <?php if (isset($errors['body'])): ?>
        <p class="field-error" id="body-error"><?= App\View::e($errors['body']) ?></p>
      <?php endif; ?>
    </div>

    <div class="field field-checkbox">
      <input type="checkbox" id="is_spoiler" name="is_spoiler" value="1"<?= $spoilerChecked ? ' checked' : '' ?>>
      <label for="is_spoiler">这条内容包含剧透</label>
      <p class="field-hint">勾选后，正文默认折叠，列表、搜索和分享摘要都不会露出原文。</p>
    </div>

    <button class="btn" type="submit">发布</button>
  </form>
</section>
