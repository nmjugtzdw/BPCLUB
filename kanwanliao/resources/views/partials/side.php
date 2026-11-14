<section class="panel side-panel">
  <p class="side-kicker">散场 · 未完</p>
  <h2>留一点余味，<br>给讨论。</h2>
  <p class="muted">不必是专业影评，一句真实感受也值得分享。</p>
  <div class="side-actions">
    <?php if ($authUser !== null): ?>
      <a class="btn" href="/publish">发起讨论</a>
      <a class="btn-secondary" href="/user/<?= (int) $authUser['id'] ?>">我的主页</a>
    <?php else: ?>
      <a class="btn" href="/login">登录</a>
      <a class="btn-secondary" href="/register">注册</a>
    <?php endif; ?>
  </div>
</section>
<section class="panel side-panel">
  <h2 class="rules-heading">讨论之前</h2>
  <ol class="community-rules">
    <li><strong>允许不同的观影感受</strong><p>可以观点相左，但请保持友善与理性。</p></li>
    <li><strong>给关键剧情留一道封签</strong><p>发布时标记剧透，让别人自主选择是否展开。</p></li>
    <li><strong>只聊作品，不找资源</strong><p>不发布或索取影视播放、下载与资源链接。</p></li>
  </ol>
</section>
<section class="panel side-panel">
  <a class="side-directory" href="/titles">作品索引 <span aria-hidden="true">↗</span></a>
  <p class="muted">从一部作品，找到另一场讨论。</p>
</section>
