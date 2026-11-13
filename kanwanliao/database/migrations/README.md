# 数据库迁移与回滚

> 当前状态：**脚本与 SQL 已写好，但尚未在任何数据库执行**（本机没有 MySQL 实例）。执行记录与实测结果以 `TASKS.md` 为准，计划不代替验收证据。

## 执行前提

1. 复制 `.env.example` 为 `.env`，填写**独立开发库**与**专用最低权限账号**；凭据不提交、不写入日志。
2. 迁移脚本有两道硬门禁：`DB_NAME` 必须以 `_test` / `_dev` / `_dev_test` 结尾，`DB_USER` 不得为 `root`。不满足直接退出，不执行任何 SQL。
3. 只允许在隔离库执行。**不得指向生产库、不得复用生产凭据、不得为本项目升级共享 MySQL 实例。**

## 命令

```bash
php scripts/migrate.php status   # 查看已应用版本
php scripts/migrate.php up       # 应用 001_init
php scripts/migrate.php down     # 回滚 001_init
```

版本号记录在 `schema_migrations` 表（由脚本自动创建）。SQL 文件按 `;` 拆句逐条执行，纯注释行会被跳过。

## 版本

| 版本 | 内容 | 回滚 |
|---|---|---|
| `001_init` | `users` / `titles` / `threads` / `comments` / `reports` / `admin_audit_logs` 六张表，主键、唯一约束、外键、索引、状态枚举 | `001_init.down.sql` 按外键依赖倒序 DROP，并删除版本记录 |

## 设计约定

- 引擎 InnoDB，字符集 `utf8mb4` / `utf8mb4_unicode_ci`，`ROW_FORMAT=DYNAMIC` 以适配 MySQL 5.7 的索引长度限制。
- 所有 `DATETIME` 存 **UTC**；连接建立后执行 `SET time_zone = '+00:00'`，应用层固定 `date_default_timezone_set('UTC')`。
- 密码只存 `password_hash`，不存明文；审计日志不记录密码或凭据。
- 删除 / 隐藏用 `status` 表达，**业务数据不做物理删除**；`threads.title_id` 用 `ON DELETE SET NULL`，删除帖子不影响作品资料。
- 同名作品靠 `uq_titles_identity (name, work_type, release_year)` 区分。

## 已知限制（必须如实记录）

- MySQL 的 DDL 会**隐式提交**，迁移不能被事务包住；半途失败可能留下部分表。回滚正确性只能靠隔离库实测 `up → down → up` 与备份恢复演练来证明。
- 目标服务器实测为 **MySQL 5.7.44**（已 EOL）。上线前需评估安全维护状态与独立实例方案，**不得为了本项目升级共享数据库**。
- P02 验收要求「迁移和回滚均在隔离库实测」。本机无 MySQL / Docker，且常驻服务无法在会话间保持，因此该实测**尚未执行**；需用户授权在服务器创建隔离测试库，或提供可用的本机数据库实例后补跑，不得标为通过。
