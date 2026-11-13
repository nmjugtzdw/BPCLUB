# 看完聊 · 影视讨论论坛工程

> 技术路线（2026-11-13 用户确认）：PHP 8.x + PDO + MySQL/MariaDB + Nginx/PHP-FPM + 原生 HTML/CSS/JS，模块化单体。
> 当前阶段 **P00–P06 已完成并上线**；需求与验收真源在项目根 `docs/影视论坛-*.md`（含 `影视论坛-安全与运维手册.md`），进度真源在项目根 `TASKS.md`，协作约定见项目根 `AGENTS.md`。

## 目录结构

```text
kanwanliao/
├── public/                 唯一 Web 根目录（部署时把站点 root 指向这里）
│   ├── index.php
│   └── assets/{css,js}/
├── app/                    引导、配置、日志、数据库、路由、视图与控制器
├── config/                 默认值与路由表
├── resources/views/        页面模板
├── database/migrations/    版本化迁移与回滚（含说明）
├── database/seeds/         演示种子（虚构，手动执行）
├── scripts/migrate.php     迁移 CLI
├── scripts/backup.sh       数据库备份（隔离库门禁 + gzip 校验 + 保留策略）
├── scripts/restore.sh      恢复到隔离目标库（强制 --yes）
├── scripts/backup-drill.sh 备份恢复演练（克隆表 + 逐表比对行数）
├── tests/run.php           零依赖测试脚本
├── docs/                   P01 内容契约与原型说明
├── storage/                日志 / 缓存 / 会话（不进版本库）
└── .runtime/               本地便携 PHP（不进版本库）
```

配置、日志、测试、迁移与备份都在 `public/` 之外；Web 根目录之外的内容不得被公网直接访问。

## 本地运行

本机没有系统级 PHP，工程内带了便携版 PHP 8.2.35（`.runtime/php82/`，已被 `.gitignore` 排除）：

```bash
PHP=kanwanliao/.runtime/php82/php.exe

# 1) 本地配置（可选；不填数据库也能看首页原型与健康检查）
cp kanwanliao/.env.example kanwanliao/.env

# 2) 启动内置开发服务器，Web 根指向 public/
"$PHP" -S 127.0.0.1:8099 -t kanwanliao/public

# 3) 真跑测试
"$PHP" kanwanliao/tests/run.php

# 4) 语法检查（所有 PHP 文件）
find kanwanliao -name '*.php' -not -path '*/.runtime/*' -exec "$PHP" -l {} \;

# 5) 迁移（需独立开发库，见 database/migrations/README.md）
"$PHP" kanwanliao/scripts/migrate.php status
```

> 本机不能保持常驻进程：开发服务器必须在单次调用内「启动 → 请求 → 停服」，验证脚本按这个模式写。

## 环境与隔离

| 项 | 要求 |
|---|---|
| PHP | 8.2.x，扩展 `pdo_mysql` / `mbstring` / `openssl`；时区 UTC |
| 数据库 | 隔离开发库 + 专用最低权限账号，utf8mb4，时间 UTC；**不得指向生产库** |
| 凭据 | 只在 `.env`（不提交）；日志与错误响应都不输出 DSN、账号、密码或堆栈 |
| 服务器 | 目标机实测 nginx 1.30.6、PHP 8.2（宝塔）、MySQL 5.7.44；部署见下节「部署（已上线）」 |

迁移脚本有两道门禁：`DB_NAME` 必须以 `_test` / `_dev` / `_dev_test` 结尾，`DB_USER` 不得为 `root`。

## 部署（已上线）

云端：**https://kwl.zzzmxxkj.com** ，站点目录 `/www/wwwroot/kwl.zzzmxxkj.com`，服务器 `ubuntu@111.231.174.142`（SSH 免密可用）。

同步一条命令（在项目根 `D:\workspace\BaiPiao-Community` 下执行）：

```bash
bash kanwanliao/scripts/deploy.sh
```

脚本只上传代码与静态资源，**不覆盖线上 `.env`、不清空 `storage/`、不删除服务器文件、不改 nginx 配置**；`.runtime/`（本机 119MB 便携 PHP）永不上传。

### 站点必须满足的三件事

| 项 | 值 | 说明 |
|---|---|---|
| 运行目录 | `/www/wwwroot/kwl.zzzmxxkj.com/public` | 面板「网站 → 设置 → 网站目录 → 运行目录」选 `/public`。指向工程根会把 `app/`、`config/`、`.env` 暴露到公网 |
| PHP 版本 | 8.2 | 面板站点已启用；FPM socket `/tmp/php-cgi-82.sock` |
| 伪静态 | `try_files $uri $uri/ /index.php?$query_string;` | 面板「伪静态」填写；实际落在 `/www/server/panel/vhost/rewrite/kwl.zzzmxxkj.com.conf` |

服务器上 `php` 命令默认是 **7.0.33**，与站点 8.2 不同；跑 CLI 测试必须用绝对路径 `/www/server/php/82/bin/php`。

### 线上环境文件

`/www/wwwroot/kwl.zzzmxxkj.com/.env`（属主 `www:www`、权限 `600`、不在 Web 根内、不进版本库）：

```ini
APP_ENV=production
APP_DEBUG=false
TIMEZONE=UTC
LOG_PATH=storage/logs/app.log
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASS=
DB_CHARSET=utf8mb4
```

`DB_NAME` / `DB_USER` 留空表示「数据库未配置」，`/health` 会返回 `not_configured`，应用不会静默连库。

### 其它站点

`/www/wwwroot` 下有 20 多个生产站点（含 `bbs`、`lingqu`），**一律不许触碰**；不手写 vhost 主配置（面板重建站点会覆盖）。

## 当前实现边界

已实现：首页 / 分类 / 作品 / 帖子详情 / 搜索（走真实数据库，服务端剧透防护）、注册 / 登录 / 退出、发帖（可关联作品与标记剧透）、评论、举报、用户公开主页、管理后台（待审队列 + 举报处置 + 账号状态 + 操作审计）、健康检查、统一错误页、日志脱敏、版本化迁移与回滚、登录与写入限流、备份与恢复演练脚本。

**未实现**：图片上传、在线播放或任何资源链接、找回密码 / 邮箱验证、私信、评分排行、移动 App。

**未验证**：Firefox / Safari / 微信内置浏览器；「新建独立库再恢复」演练（账号无建库权限）；用户协议、隐私政策、素材许可与备案资质等业务合规项。

安全措施、限流阈值、备份恢复步骤与上线检查见 `docs/影视论坛-安全与运维手册.md`；未完成项与证据见 `TASKS.md`，不得把计划或代码存在当成验收通过。
