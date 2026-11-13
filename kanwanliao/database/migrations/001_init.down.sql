-- 001_init 回滚：按外键依赖倒序删除，只允许在独立开发库执行。
-- MySQL DDL 会隐式提交，无法用事务包住；回滚正确性只能靠隔离库实测保证。

DROP TABLE IF EXISTS admin_audit_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS threads;
DROP TABLE IF EXISTS titles;
DROP TABLE IF EXISTS users;
