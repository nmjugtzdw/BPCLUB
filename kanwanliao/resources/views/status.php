<?php
/** @var int $code */
/** @var string $title */
/** @var string $message */
?>
<section class="panel status-panel" aria-labelledby="status-title">
  <p class="status-code"><?= App\View::e($code) ?></p>
  <h1 id="status-title"><?= App\View::e($title) ?></h1>
  <p class="muted"><?= App\View::e($message) ?></p>
  <p><a class="button-link" href="/">回到首页</a></p>
</section>
