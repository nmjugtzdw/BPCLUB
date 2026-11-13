<?php
/**
 * @var App\Pager $pager
 * @var string $basePath
 * @var array<string, string> $extraQuery 额外保留的查询参数（如搜索词）
 */
$extraQuery = $extraQuery ?? [];
$link = static function (int $page) use ($basePath, $extraQuery): string {
    $query = $extraQuery;
    if ($page > 1) {
        $query['page'] = (string) $page;
    }
    $qs = http_build_query($query);
    return $basePath . ($qs === '' ? '' : '?' . $qs);
};
?>
<?php if ($pager->pages > 1): ?>
  <nav class="pager" aria-label="分页">
    <?php if ($pager->hasPrev()): ?>
      <a class="pager-link" rel="prev" href="<?= App\View::e($link($pager->page - 1)) ?>">上一页</a>
    <?php else: ?>
      <span class="pager-link is-disabled" aria-disabled="true">上一页</span>
    <?php endif; ?>

    <span class="pager-status">第 <?= (int) $pager->page ?> / <?= (int) $pager->pages ?> 页 · 共 <?= (int) $pager->total ?> 条</span>

    <?php if ($pager->hasNext()): ?>
      <a class="pager-link" rel="next" href="<?= App\View::e($link($pager->page + 1)) ?>">下一页</a>
    <?php else: ?>
      <span class="pager-link is-disabled" aria-disabled="true">下一页</span>
    <?php endif; ?>
  </nav>
<?php endif; ?>
