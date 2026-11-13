-- 002_auth_and_safety 回滚：只删除本迁移新增的对象，不动 001 的结构与数据。

SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE reports DROP KEY idx_reports_target;
ALTER TABLE comments DROP KEY idx_comments_status;
DROP TABLE IF EXISTS rate_limits;
