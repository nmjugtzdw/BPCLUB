-- 002_auth_and_safety · 注册登录、发帖评论与审核所需的增量结构
-- 只能在独立开发库执行；所有 DATETIME 一律 UTC。
-- 本迁移只新增表与索引，不改动既有列，便于单独回滚。

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 固定窗口计数表：登录、注册、发帖、评论、举报的滥用控制。
-- 只存桶名与键的散列，不存明文 IP 或账号，减少隐私暴露。
CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket VARCHAR(32) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  counter INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_window (bucket, key_hash, window_start),
  KEY idx_rate_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- 审核队列按「状态 + 时间」取待处理内容；原索引以 thread_id 开头，无法覆盖全站队列。
ALTER TABLE comments ADD KEY idx_comments_status (status, created_at);

-- 举报去重与队列定位需要按对象查。
ALTER TABLE reports ADD KEY idx_reports_target (target_type, target_id);
