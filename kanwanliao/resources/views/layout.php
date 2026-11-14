<?php
/** @var string $content */
/** @var array<string, mixed> $data */
$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : '看完聊';
$navKey = isset($navKey) && is_string($navKey) ? $navKey : '';
// 分享摘要由控制器传入，剧透帖只会得到提示文本，不含正文。
$metaDescription = isset($metaDescription) && is_string($metaDescription) && $metaDescription !== ''
    ? $metaDescription
    : '看完聊：电影和电视剧讨论论坛。';
$searchQuery = isset($query) && is_string($query) ? $query : '';
$isDiscussionList = in_array($navKey, ['home', 'category'], true);
// HTML 与样式同步更新，避免浏览器复用旧布局样式。
$styleVersion = substr(hash_file('sha256', dirname(__DIR__, 2) . '/public/assets/css/app.css'), 0, 12);
$authUser = (isset($authUser) && is_array($authUser)) ? $authUser : null;
$isAdmin = isset($isAdmin) && $isAdmin === true;
$csrfToken = isset($csrfToken) && is_string($csrfToken) ? $csrfToken : '';
$flash = (isset($flash) && is_array($flash) && isset($flash['message'])) ? $flash : null;
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="<?= App\View::e($metaDescription) ?>">
  <meta property="og:title" content="<?= App\View::e($pageTitle) ?>">
  <meta property="og:description" content="<?= App\View::e($metaDescription) ?>">
  <title><?= App\View::e($pageTitle) ?> · 看完聊</title>
  <link rel="stylesheet" href="/assets/css/app.css?v=<?= App\View::e($styleVersion) ?>">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">跳到主要内容</a>

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/" aria-label="看完聊首页"><span class="brand-mark" aria-hidden="true"></span>看完聊</a>
      <nav class="site-nav" aria-label="主导航">
        <a href="/"<?= $navKey === 'home' ? ' aria-current="page"' : '' ?>>全部讨论</a>
        <?php foreach (App\Category::all() as $slug => $name): ?>
          <a href="/category/<?= App\View::e($slug) ?>"<?= ($activeCategory ?? null) === $slug ? ' class="is-active" aria-current="page"' : '' ?>><?= App\View::e($name) ?></a>
        <?php endforeach; ?>
      </nav>
      <form class="header-search" action="/search" method="get" role="search">
        <label class="visually-hidden" for="header-q">搜索作品或话题</label>
        <input id="header-q" type="search" name="q" maxlength="60" placeholder="搜索讨论、作品…" value="<?= App\View::e($searchQuery) ?>">
        <button type="submit" aria-label="搜索">搜索</button>
      </form>
      <div class="account-nav" aria-label="账户操作">
        <?php if ($authUser !== null): ?>
          <a href="/user/<?= (int) $authUser['id'] ?>"<?= $navKey === 'user' ? ' aria-current="page"' : '' ?>><?= App\View::e((string) $authUser['username']) ?></a>
          <?php if ($isAdmin): ?>
            <a href="/admin"<?= $navKey === 'admin' ? ' aria-current="page"' : '' ?>>管理</a>
          <?php endif; ?>
          <form class="nav-form" method="post" action="/logout">
            <input type="hidden" name="csrf" value="<?= App\View::e($csrfToken) ?>">
            <button type="submit" class="nav-button">退出</button>
          </form>
        <?php else: ?>
          <a href="/login"<?= $navKey === 'login' ? ' aria-current="page"' : '' ?>>登录</a>
          <a class="register-link" href="/register"<?= $navKey === 'register' ? ' aria-current="page"' : '' ?>>注册</a>
        <?php endif; ?>
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="切换深浅主题">明暗</button>
      </div>
    </div>
  </header>

  <div class="container demo-banner" role="note">
    <span>散场之后 <span class="edition-en" lang="en">/ AFTER THE CREDITS</span></span>
    <span><strong>筹备版本</strong> · 作品与部分讨论为示例，发布内容会真实保存。</span>
  </div>

  <?php if ($isDiscussionList): ?>
    <section class="container masthead" aria-labelledby="masthead-title">
      <div><p class="eyebrow"><span>01</span> / 放映结束，讨论开始</p><h1 id="masthead-title"><span>看完了，</span><span>话还没说完。</span></h1></div>
      <a class="button-link" href="/publish">＋ 发起讨论</a>
    </section>
  <?php endif; ?>

  <div class="container layout">
    <main id="main" class="main-column">
      <?php if ($flash !== null): ?>
        <div class="alert alert-<?= ($flash['type'] ?? 'ok') === 'error' ? 'error' : 'ok' ?>" role="status">
          <?= App\View::e((string) $flash['message']) ?>
        </div>
      <?php endif; ?>
      <?= $content ?>
    </main>
    <aside class="side-column" aria-label="社区信息">
      <?php require __DIR__ . '/partials/side.php'; ?>
    </aside>
  </div>

  <footer class="site-footer">
    <div class="container">
      <p><strong>看完聊</strong> <span>/</span> 看完一部，聊聊感受</p>
      <p class="muted">筹备版本，内容为演示示例；不提供影视播放、下载或资源链接。</p>
    </div>
  </footer>
</body>
</html>
