# TASKS.md — 看完聊（影视讨论论坛）进度真源

> **当前进度（2026-11-14 02:20）**：**P00–P06 全部完成**，功能已上线 `https://kwl.zzzmxxkj.com/`。现在可以注册、登录、发帖、评论、举报，管理员可审核与处置。`tests/run.php` **122 passed / 0 failed**；剧透防护、权限边界、CSRF、限流、审计日志均已在线实测；7 个页面 × 桌面 1440 / 移动 375 × 深浅主题共 28 次加载，**798 处文本 WCAG AA 全通过**，横向溢出与键盘焦点问题均为 0；备份与同库恢复演练通过（8 表行数全一致）。**线上已回到演示种子状态**：3 个演示账号、9 帖、4 评论，举报 / 限流 / 审计表均为 0，无验收残留账号。**未完成**：独立审计、用户签收、品牌名确认、跨浏览器（Firefox/Safari/微信）实测、业务合规项（用户协议/隐私/素材许可/备案）、独立库恢复演练。

> **云端已上线（2026-11-13 23:10）**：`https://kwl.zzzmxxkj.com/` 已从宝塔默认页切换为本项目首页，**P01 原型可在云端实测**。用户已授权所有 Agent 直接上传，命令见 `AGENTS.md` §3.1。
>
> **P02 数据库已闭环（2026-11-13 23:00）**：用户提供 MySQL 密码后，已建隔离库 `kanwanliao_dev` + 专用账号（非 root、仅本库权限），**迁移 up / down / 重建全部实测通过**，线上 `/health` 由 `not_configured` 变为 `database: ok`（PHP-FPM 真实连库）。账号 / 发帖 / 评论 / 审核仍未实现（属 P04–P05），当前只有只读查询。

> **影视论坛域名（2026-11-13）**：用户指定 `kwl.zzzmxxkj.com`，并表示已完成解析；尚未独立核验 DNS、宝塔站点、TLS 或部署状态。本次未修改服务器。

> **用户方向确认（2026-11-13）**：坚定推进影视论坛，旧 V1 和 V2 可以删除；本机工作区规则禁止 Agent 执行删除，本轮未删除任何文件。影视论坛文档、效果图、候选 Logo 和 `prototypes/kanwanliao/` 继续保留；具体清理清单及正式技术路线待落实。

> **影视论坛 P00（2026-11-13）**：完成本地只读盘点和 `docs/P00-现状核查与隔离方案.md`；命令工具报告 Windows CET 错误，Git/PHP/Node/npm 未能核验，服务器未连接。技术路线、隔离目录与迁移策略待用户确认；独立审计及验收未完成，不进入 P01。

> **影视论坛开工原型（2026-11-13）**：用户要求先写代码供其他 Agent 接手；新增独立 `prototypes/kanwanliao/` 静态原型（首页、示例详情、样式和筛选脚本），不替换 V2。终端执行遇到 Windows CET 错误，PHP/Node 命令与浏览器交互尚未验证；P00 技术与迁移确认、独立审计和用户验收仍待完成。

> **影视论坛视觉参考（2026-11-13）**：新增 `docs/影视论坛-效果图说明.md` 与 `docs/images/影视论坛-界面参考.png`，覆盖桌面首页、影视详情、移动端；仅为视觉参考，不代表功能实现或验收。

> **影视论坛新方向（2026-11-13）**：V1/V2/V3 不再作为产品方向。新增 `docs/影视论坛-项目白皮书与总体方案.md`、`docs/影视论坛-分阶段实施与任务清单.md`、`docs/影视论坛-审计与验收方案.md`。~~P00–P06 全部待执行~~ → ~~P04–P06 待执行~~ → **截至 2026-11-14 02:20：P00–P06 全部完成并上线，待用户签收**；以下 V2 记录保留历史，不代表影视论坛开发或验收结果。

> 只记**真实执行结果**；未执行写明原因；计划不代替验收证据。
> 每轮结束由协调者更新；用户确认前不进入下一轮。
> 协作约定见 `AGENTS.md`，工作区规矩见 `D:\workspace\规矩文档.md`，面向读者的说明见 `README.md`。
> 最近更新：2026-11-14 02:20（P04/P05/P06 完成并上线；测试 122/122；备份恢复演练通过；线上已回落演示种子状态）

## 影视论坛 P00 — 本轮真实结果（2026-11-13 22:15–22:23）

> 用户指令：「我又让别的 agent 写了新的白皮书，域名我也改了 kwl.zzzmxxkj.com，然后准备做电影电视剧影评，之前 v1 和 v2 的项目不需要的代码文件都可以删了，你开始做我们新的项目吧」。
> **本轮定位**：把 P00 剩下的环境核查补齐、按授权隔离旧代码、把共享文档切到新方向。**没有搭建新工程、没有部署、没有 `git init`、没有删除任何文件**（只移动），因为 P00 的技术路线门禁尚未通过。

### 只读核查（真实命令输出，2026-11-13 22:15；站点状态已于 23:10 部署后变化，见下节）

| 检查 | 结果 |
|---|---|
| 本地 Git | `git status --short --branch` → `fatal: not a git repository (or any of the parent directories): .git`，**无 CET 错误**；`.git` 目录不存在 |
| 本地运行时 | Node 22.22.2 / npm 10.9.7；`php -v` → `command not found`（**本机无 PHP**） |
| 并发修改 | `AGENTS.md` 21:39、`docs/` 21:51、`prototypes/` 21:48、`TASKS.md` 22:06（北京时间）——其它 Agent 刚写过文档；本轮保留 `TASKS.md` 顶部其原有说明，未覆盖 |
| 服务器运行时 | SSH 只读：nginx 1.30.6；PHP CLI 默认 7.0.33，宝塔装 70 / 74 / **82**（82 有 `pdo_mysql`、`mbstring`、`openssl`）；MySQL **5.7.44**；磁盘 36G 可用；`/www/wwwroot` 25 个站点目录 |
| 新站点 | `/www/wwwroot/kwl.zzzmxxkj.com` 已建（22:03），含 `404.html` / `.htaccess` / `.user.ini` / `.well-known/` / 默认 `index.html`；`nginx -T` 中 vhost 可见：listen 80/443、`root` 正确、http 301 → https、证书 `CN=kwl.zzzmxxkj.com`（2026-11-13 → 2027-02-11）、`include enable-php-00.conf` → **未启用 PHP，当前纯静态** |
| 新站 `.user.ini` | `lsattr` 显示 `----i---------e-------`，与旧站一样带 immutable 属性 |
| 域名可达性 | `http://kwl.zzzmxxkj.com/` → 301 → `https://kwl.zzzmxxkj.com/` **200 / 769 字节**，内容为宝塔「恭喜，站点创建成功！」；本机 `dns.resolve4` 返回 `ECONNREFUSED`（系统解析器另走通道，不影响 HTTP 实测） |
| 旧站点 | `bp.zzzmxxkj.com` 目录 **不存在**，`nginx -T` 中 `server_name bp.zzzmxxkj.com` 匹配数 **0**；旧 V2 静态预览已下线 |

### 旧代码隔离（已执行）

- 按用户授权清理 V1/V2；按 `D:\workspace\规矩文档.md` §3.9 **Agent 不执行删除、不换写法绕过**，改为整体 `renameSync` 移动到 `D:\workspace\_待删\BaiPiao-Community-V1V2-20261113\`。
- 结果：`ok=24 skip=1 fail=0`（`dist` 是指向 `.output/public` 的**断链符号链接**，`existsSync` 为 false，第二次用 `lstatSync` 判定后单独移走），合计 **25 项**。根目录只剩 `.workbuddy/`、`AGENTS.md`、`docs/`、`prototypes/`、`TASKS.md`。
- 隔离区 `README.md` 写明清单、恢复方式与用户可执行的 `rd /s /q` 删除命令；旧 V2 `README.md` 改名 `README.v2.md` 避免同名冲突。
- **保留未动**：`docs/`（影视四份 + P00 + 效果图 + V1/V2/V3 历史）、`prototypes/kanwanliao/`、`.workbuddy/`、`AGENTS.md`、`TASKS.md`。
- 服务器本轮**零写入、零删除、零部署**。

### 文档切换（已执行）

- `AGENTS.md` 全面重写为影视论坛基线：轮次（P00 待验收）、剧透防泄露与内容真实性契约、实测环境与技术路线待确认、新域名与生产边界、旧代码隔离说明、文件所有权、硬禁令、对外文案铁律。**去掉全部 V2 产品/技术细节**，避免其它 Agent 按废弃方案开发。
- `TASKS.md` 保留其它 Agent 已写的 5 条影视进度说明，改标题、追加本轮真实结果，并把下方 V2 正文标为历史记录。
- `README.md` 换成新项目读者视角说明。

### P00 门禁结果（22:30 技术路线已确认）

- [x] 用户选择 **PHP 8.2 + MySQL（推荐）**：PHP 8.x + PDO + MySQL/MariaDB + Nginx/PHP-FPM + 原生 HTML/CSS/JS 模块化单体；并同意准备本地 PHP 运行环境。
- [x] 新工程目录定为 `kanwanliao/`，第一阶段目标 P01 + P02。
- [ ] 品牌名称仍用工作名「看完聊」，待用户最终确认；独立审计与用户签收未完成。
- 旧 V2 的 42 条单测、旧静态产物、旧云端页面一律不作为影视论坛验收证据。

## 影视论坛 P01 / P02 — 本轮真实结果（2026-11-13 22:30–22:45）

### 环境与工程落地

- 本机原本无 PHP / MySQL / Docker / Composer、3306 未监听；从 `windows.php.net` 下载 **PHP 8.2.35 NTS x64** 便携版（33,780,725 字节），解压到 `kanwanliao/.runtime/php82/` 并写 `php.ini`（`extension_dir` 绝对路径，启用 `pdo_mysql` / `mbstring` / `openssl` / `fileinfo` / `curl`，`date.timezone=UTC`）。实测 `php -v` → `PHP 8.2.35 (cli)`，`php -m` 三个关键扩展齐全，`date_default_timezone_get()` → `UTC`。
- 新建工程 `kanwanliao/`，`public/` 为唯一 Web 根；配置、迁移、日志、测试、备份都在根之外。共 **21 个 PHP 文件** + CSS/JS + 3 个 SQL + 说明文档 + `.gitignore` / `.env.example`。
- 本地 PHP 便携版与 `.env`、`storage/` 均已排除出版本库；**没有创建任何含真值的 .env**。

### 真跑结果（原始输出摘要）

| 命令 / 检查 | 真实结果 |
|---|---|
| `php -l` 遍历 21 个 PHP 文件 | **21 文件 / 0 语法错误** |
| `php kanwanliao/tests/run.php` | **33 passed, 0 failed**（配置缺失失败、UTC、路由与参数、路径穿越拒绝、剧透 6 项、首页渲染与剧透正文不泄露、404、健康检查、XSS 转义、日志脱敏、DB 配置缺失失败） |
| 内置开发服务器冒烟（单次调用内启动→10 个请求→停服） | **10/10 PASS**：`/` 200 / 4374 字节含演示声明与剧透占位、**不含剧透示例正文**；`/health` 200 JSON；未知路径 404；CSS 200 `text/css`；JS 200；`/app/Config.php` 与 `/.env` 均 404（Web 根之外不可访问） |
| `php scripts/migrate.php status`（无 .env） | exit 1，输出「拒绝执行：DB_NAME 必须是隔离测试库」→ **安全门禁生效，未连接任何数据库** |
| 服务器写入 / 部署 / Git | **全部为零**：未上传、未建库、未启用 PHP、未 `git init`、未推送 |

### 已实现与未实现

- 已实现：首页原型（虚构示例 + 演示声明 + 深浅主题 + 响应式）、健康检查、配置加载与缺失失败、统一错误页（不泄露堆栈）、日志脱敏、PDO 连接工厂、路由与 404、剧透输出规则真源 `app/Spoiler.php`、六张表的版本化迁移与回滚 SQL、迁移 CLI（隔离库门禁）。
- 已实现（P03）：只读论坛——帖子列表 / 帖子详情 / 作品列表 / 分类页 / 搜索，全部走真实数据库查询；服务端剧透防护（列表、标题、摘要、搜索、meta 描述逐项核对）；深浅主题 WCAG AA 达标。
- 未实现：账号注册登录、发帖、评论、审核举报、管理后台、图片上传、播放或资源链接；界面入口都是标注「筹备中」的不可点占位。

### 遗留风险 / 未验证（不得标为通过）

- ~~数据库迁移 / 回滚 / 重建的隔离库实测未执行~~ → **2026-11-13 23:00 已补跑通过**，见下节「P02 数据库闭环」。本机仍无 MySQL，后续本机侧迁移只能连服务器开发库。
- ~~**未做桌面 / 移动浏览器视觉走查**、键盘焦点路径与深色主题对比度人工检查~~ → **2026-11-14 00:58 已补做视觉走查与深浅主题对比度审计**（浅色/深色各 252 处文本、0 失败）；**键盘 Tab 焦点路径走查仍未做**。
- 目标服务器 MySQL 5.7.44 已 EOL，上线前须评估独立实例；不得为本项目升级共享数据库。**当前 `kanwanliao_dev` 与 20 多个生产站点同实例，仅限开发阶段使用。**
- 项目仍不是 Git 仓库；品牌名称待确认；独立审计与用户签收未完成；**P03 已完成并上线，P04–P06 未开工**。

## 影视论坛 P01 云端部署 — 本轮真实结果（2026-11-13 22:50–23:10）

> 用户指令：「你同步到云服务器上了吗？你应该是可以同步上去吧 /www/wwwroot/kwl.zzzmxxkj.com，宝塔需要我怎么设置你给我说都行」+「对了你也顺便告诉其他 agent 可以上传到宝塔」。
> **本轮定位**：把 P01 原型部署到云端供用户实测，打通「本机同步 → 服务器运行」全链路。**没有建数据库、没有新增功能、没有 `git init`。**

### 宝塔侧改动（由 Agent 通过 SSH 完成，用户无需再操作）

| 项 | 改动前 | 改动后 | 位置 |
|---|---|---|---|
| 运行目录 | `/www/wwwroot/kwl.zzzmxxkj.com` | `/www/wwwroot/kwl.zzzmxxkj.com/public` | vhost `root` 行；原文件已备份 `.bak-<时间戳>` |
| 伪静态 | 文件存在但 **0 字节** | `try_files $uri $uri/ /index.php?$query_string;` + favicon/robots 静默 | `/www/server/panel/vhost/rewrite/kwl.zzzmxxkj.com.conf` |
| PHP 版本 | `enable-php-00.conf`（未启用 PHP） | **8.2**（`enable-php-82.conf`） | 面板已选 8.2，vhost 已生效 |
| 线上 `.env` | 不存在 | `APP_ENV=production` / `APP_DEBUG=false` / `TIMEZONE=UTC` / DB 全空 | 站点根，`www:www` `600` |

未改：证书、http→https 跳转、`.well-known/`、`.user.ini`、`404.html`、`.htaccess`，以及其它 20 多个站点。

### 同步脚本（新建）

`kanwanliao/scripts/deploy.sh`：`tar czf -` 打包 10 项 → SSH 管道 → 服务器 `sudo tar xzf -` → `find ... ! -name '.user.ini' ! -name '.env' -exec chown www:www {} +` → 自检 `/health`。排除 `.runtime/`、`.env`、`storage/logs/*.log`、`storage/cache/*`、`storage/sessions/*`。**覆盖式，不删服务器文件。**

### 真跑结果

| 检查 | 真实结果 |
|---|---|
| 服务器语法检查（`/www/server/php/82/bin/php -l`） | **18 文件 / 0 错误**（系统 `php` 7.0.33 会报 Parse error，已改用 8.2 绝对路径） |
| `bash kanwanliao/scripts/deploy.sh` | **EXIT=0**，上传完成，自检 `/health` 返回 `production` |
| 外网 `https://kwl.zzzmxxkj.com/` | **200 / 4374 字节**；标题「最近大家在聊 · 看完聊」；含「筹备」「这段内容包含剧透」「风起小城」 |
| `/health` | **200 JSON**，`env` 已从 `local` 切到 `production`，`database` → `not_configured` |
| 未知路径 `/no-such-page` | **404**（统一错误页，不泄露堆栈） |
| CSS / JS | **200**，MIME 正确 |
| `/app/Config.php`、`/.env` | **均 404** → Web 根隔离生效，敏感文件不可从公网访问 |
| http → https | **301**，符合预期 |

### 未做 / 未通过

- **数据库零配置**：服务器 MySQL root 无密码连不上（`ERROR 1045`），无法建隔离库；`/health` 显示 `not_configured`。P02 迁移实测仍未通过。→ **已于 23:00 补跑通过，见下节。**
- **未做人工浏览器视觉 / 键盘走查**：仅 HTTP 层与内容断言。
- 服务器 `sudo -u www` 跑 `tests/run.php` 报 `storage/logs/` Permission denied，属权限问题非代码缺陷；测试仍以本机 33/33 为准。

## 影视论坛 P02 数据库闭环 — 本轮真实结果（2026-11-13 22:56–23:05）

> 用户提供 MySQL 密码后开跑。**本轮只做数据库：建隔离库、建专用账号、迁移实测、修一个真实时区缺陷。没有新增业务功能。**

### 建库与账号（服务器实测）

| 项 | 结果 |
|---|---|
| MySQL 连接 | `SELECT VERSION()` → **5.7.44-log**，`character_set_server` = `utf8mb4`，`sql_mode` = `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION` |
| 隔离库 | `kanwanliao_dev`，`DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` |
| 专用账号 | `kanwanliao_dev`@`127.0.0.1` 与 `@localhost`，**不是 root** |
| 授权范围 | 仅 `GRANT ALL ON kanwanliao_dev.*`；全局只有 `USAGE` |
| 权限边界实测 | `SHOW DATABASES` 只看到 `information_schema` + `kanwanliao_dev`；`SELECT FROM mysql.user` → **ERROR 1142 拒绝**；`CREATE DATABASE` → **ERROR 1044 拒绝** |
| root 凭据落地位置 | **仅服务器 `.env`（`www:www` `600`）**，未写入仓库任何文件、未写入本文档、未写进 `.env.example` |

### 迁移实测（临时副本 `/tmp/kwl-migrate-test`，不碰线上 `.env`）

| 步骤 | 结果 |
|---|---|
| `status`（未应用） | exit 0，`001_init: 未应用` |
| `up` | exit 0，`001_init 已应用` |
| `status`（已应用） | exit 0，`001_init: 已应用` |
| `up` 重复执行 | exit 0，`已应用，跳过` → **幂等** |
| 建表结果 | **7 张表**：`users` `titles` `threads` `comments` `reports` `admin_audit_logs` `schema_migrations` |
| `down` | exit 0，`001_init 已回滚` |
| 回滚后表清单 | **只剩 `schema_migrations`** → 回滚干净，无残留 |
| `down` 重复执行 | exit 0，`未应用，无需回滚` → **幂等** |
| 重建 `up` | exit 0，`001_init 已应用` → **可重建** |

### 结构级验收（`information_schema` 实查）

- 6 张业务表全部 `InnoDB` / `utf8mb4_unicode_ci` / `ROW_FORMAT=Dynamic`。
- **7 个外键全部存在**，删除规则符合设计：`fk_threads_author` `RESTRICT`、`fk_threads_title` `SET NULL`、`fk_comments_thread` `RESTRICT`、`fk_comments_author` `RESTRICT`、`fk_reports_reporter` `RESTRICT`、`fk_reports_handler` `SET NULL`、`fk_audit_actor` `RESTRICT`。
- 索引齐全：`idx_threads_listing(status,created_at)`、`idx_comments_thread(thread_id,status,created_at)`、`idx_reports_queue(status,created_at)`、`uq_users_username`、`uq_users_email`、`uq_titles_identity(name,work_type,release_year)` 等。

### 约束与编码实跑（真实 INSERT 后回读）

| 检查 | 结果 |
|---|---|
| 唯一键 | 重复 username → **SQLSTATE 23000 拒绝** |
| 外键 RESTRICT | `threads.author_id = 999999` → **SQLSTATE 23000 拒绝** |
| 中文写入回读 | `风起小城·测试` → 原样返回，无乱码 |
| 会话时区 | `@@session.time_zone` = **+00:00** |
| 时间一致性 | PHP UTC now 与 `UTC_TIMESTAMP()` **同一秒**（14:59:37） |

### 发现并修复的真实缺陷

- **问题**：`scripts/migrate.php` 直接 require `Config` / `Database`，**绕过了 `Bootstrap::create()`**，而 `date_default_timezone_set('UTC')` 只写在 Bootstrap 里。实测该脚本运行时 PHP 时区是服务器 `php.ini` 的 **`PRC`**，与库内 UTC 差 8 小时——写库时间会错位。
- **修复**：在 `migrate.php` 加载配置后显式 `date_default_timezone_set('UTC')`，并加注释说明原因。
- **验证**：修复后 `date_default_timezone_get()` → **UTC**，PHP 时间与 `UTC_TIMESTAMP()` 一致；`php -l` 无语法错误；本机 `tests/run.php` 仍 **33/33**。

### 安全门禁实测（真实 exit code，非 grep 退出码）

| 场景 | 结果 |
|---|---|
| `DB_NAME=kanwanliao_prod` | **exit 1**，`拒绝执行：DB_NAME 必须是隔离测试库` |
| `DB_USER=root` | **exit 1**，`拒绝执行：迁移不得使用 root 账号` |
| `DB_USER` 留空 | **exit 1**，`拒绝执行：数据库配置不完整` |

### 线上联通结果

- 线上 `.env` 写入开发库连接信息（`www:www` `600`，改前已备份 `.bak-<时间戳>`）。
- `https://kwl.zzzmxxkj.com/health` → `{"app":"ok","env":"production","timezone":"UTC","database":{"status":"ok"},"stage":"P01/P02"}` —— **`database` 由 `not_configured` 变为 `ok`**，说明 PHP-FPM 以 `www` 身份真实连上了库，不只是 CLI 能连。
- 首页仍 **200 / 5661 字节**，无回归。
- 临时测试副本 `/tmp/kwl-migrate-test` 已删除（确认不存在）。

### 仍未做

> 以下三条为 **2026-11-13 23:10 时点**的状态快照，仅作历史追溯；P03 已于 2026-11-14 完成并上线，现状见本文档末尾「P03 完成记录」。

- **没有任何业务读写代码**：7 张表是空结构，首页 4 条示例帖仍来自 `HomeController` 的硬编码数组，不查库。
- 未做账号注册 / 登录 / 发帖 / 评论 / 搜索 / 审核，P03–P06 未开工。
- 未做浏览器视觉与键盘走查。

---

## 以下为 V2 历史记录（仅追溯，不代表影视论坛进度或验收结果）

## 当前轮次

**V2 第一轮**：用户 2026-11-13 14:03 明确「决定按照 V2 做，V1 是不成熟的计划，范围太广，可以舍弃」。

## 方向与 V1 处置

| 项 | 处置 |
|---|---|
| V1 产品方案 | 整体舍弃。`docs/项目白皮书.md`、`docs/第一阶段开发计划.md` 已由用户标注 V1.0 废弃，只作历史 |
| V1 源码 | **零删除**：`types/ data/ repositories/ utils/ tests/ components/ layouts/ pages/ composables/ assets/` 共 10 个目录整体移动到 `legacy/v1/` 冻结，新增 `legacy/v1/README.md` |
| 归档隔离 | `tsconfig.json` exclude、`eslint.config.mjs` ignores、`vitest.config.ts` exclude 均含 `legacy`；归档不参与构建/类型检查/lint/测试 |
| V2 需求基线 | `docs/薅客Token-V2迁移方案.md`（用户文档，未经许可不改写） |
| V2 文档过时盘点 | 该方案 §2 / §6 写于迁移前，「未验证可运行性」「git status 因 CET 失败」已被实测推翻；**现状一律以本文件和源码为准** |

## 开工核查（只读，真实结果）

| 检查 | 结果 |
|---|---|
| Git | `git status --short --branch` → `fatal: not a git repository (or any of the parent directories): .git`；**项目仍不是 Git 仓库**，无 CET 错误。`AGENTS.md` 旧版里「已做首次基线提交」是错的，已修正 |
| 并发修改 | 源码最后改动 13:52、三份 docs 均为 13:57:52；开工前重查无更新，未覆盖其它 Agent 的工作 |
| 本地环境 | Node 22.22.2 / npm 10.9.7；`nuxt@3.21.11` engines 满足；服务器 Node 18.19.1 不满足 |
| 云端 | `https://bp.zzzmxxkj.com/` 强制 HTTPS；SSH 免密 + `sudo -n` 可用；站点根目录 `/www/wwwroot/bp.zzzmxxkj.com` |

## V2 第一轮任务清单

| # | 任务 | 状态 | 证据 |
|---|---|---|---|
| 1 | V1 归档、配置基线（排除归档、V2 标题、详情预渲染枚举） | 已完成 | `legacy/v1/`、`tsconfig.json`、`eslint.config.mjs`、`vitest.config.ts`、`nuxt.config.ts` |
| 2 | Provider / Offer / OfferRepository 类型契约（不继承论坛字段） | 已完成 | `types/index.ts` |
| 3 | 18 条固定示例活动 + 10 个虚构平台，无真实链接 | 已完成 | `data/{providers,offers,index}.ts`；`tests/mockData.spec.ts` |
| 4 | 状态纯函数、固定演示时间点、格式化 + 42 条单测 | 已完成 | `utils/{offerStatus,format}.ts`、`tests/` 3 个文件 |
| 5 | 全站布局、深浅主题、演示声明、响应式双栏首页 | 已完成 | `assets/css/main.css`、`composables/useTheme.ts`、9 个组件、`layouts/default.vue`、`pages/index.vue` |
| 6 | `/offer/[id].vue` 详情与缺失空状态 | 已完成 | 18 个详情 HTML 全部预渲染 |
| 7 | `README.md` / `AGENTS.md` / `TASKS.md` 切到 V2 基线 | 已完成 | 三个文件 |
| 8 | typecheck / lint / test / build / generate 真跑 + 产物断言 | 已完成 | 见下节真实输出 |
| 9 | 同步云端并验证首页与详情 | 已完成 | 见下节 |
| 10 | 修改记录、项目记忆登记 | 已完成 | `D:\workspace\修改记录.md`、`.workbuddy/memory/` |

## 实际运行结果（命令 → 输出摘要）

| 命令 / 检查 | 真实结果 |
|---|---|
| `npm run test`（首次） | exit 0，但 **74 条**（V2 42 + 归档 V1 32）—— Vitest 扫到了 `legacy/v1/tests/`。新增 `vitest.config.ts` 排除 `legacy/**` 后复跑 → **3 文件 / 42 条全通过**（状态 20、数据 12、仓库 10） |
| `npm run lint` | exit 0，无报错 |
| `npm run typecheck`（首次） | **exit 2**：25 个 `TS2304 Cannot find name`（`ref` / `computed` / `useAsyncData` / `useTheme` / `defineNuxtConfig` 等）+ 1 个 `TS7053`。原因：源码目录整体替换后 `.nuxt` 自动导入类型未重建，且根 `tsconfig.json` 误把 `.nuxt` 放进 `exclude` |
| `npx nuxi prepare` → `npm run typecheck` | `Types generated in .nuxt`；去掉 `.nuxt` 排除后复跑 **exit 0**，无报错 |
| `npm run build` | exit 0。Nuxt 3.21.11 / Nitro 2.13.6 / Vite 7.3.8 / Vue 3.5.45；client 2241 modules、server 106 modules；Nitro 预渲染 19 initial routes → 38 routes；总产物 3.34 MB |
| `npm run generate` | exit 0，`Nitro preset: static`；预渲染 21 initial routes → **40 routes**（首页、404/200、18 个详情及各自 payload）。WARN：`cache-driver.js could not be resolved`（Nitro 已知告警，不影响产物） |
| 本地产物断言 | `index.html` 含 V2 品牌、演示横幅、活动标题、状态说明、「即将开放」；`offer/` 下 **18 个目录全部有 `index.html`**；抽查 3 个详情含演示额度 / 官方领取入口 / 演示声明；首页 8 个 `/_nuxt/` 引用文件全部存在；首页无外部链接；产物不含 `legacy` → **PASS** |
| `bash deploy/sync.sh` | **exit 0**，输出「同步完成：https://bp.zzzmxxkj.com/」 |
| 云端验证 | http 301 → https；首页 **200 / 90955 字节**，含「薅客 Token」「这是演示版本」等，**不含「白嫖社区」**；5 个静态资源抽查全部 200；3 个详情页（无斜杠 301 → 带斜杠 **200 / 约 25.5 KB**）内容齐全；详情 payload 200 / 790 字节；未知路径 404 → **PASS** |
| 本地 dev 冒烟 | 单次调用内 `nuxt dev --port 3099` 启动 → 请求 → 停服：首页 200 / 86638 字节含品牌，详情 200 / 12644 字节含演示额度 → **PASS** |

## 遗留风险 / 未验证项

- **未做人工视觉与键盘走查**：桌面双栏、移动单栏、深浅主题对比度、Tab 焦点路径只做了代码与 HTTP 层验证，未用浏览器逐项点击，不虚报为已验证。
- **静态站点路由行为**：`/offer/<id>` 无尾斜杠会 301 到 `/offer/<id>/` 再 200；未知路径仍 404。已预渲染全部 18 个详情，不需 `try_files` 兜底，也不要因此改成 SSR。
- **服务器旧产物残留**：`deploy/sync.sh` 是覆盖式 tar，V1 时期带 hash 的 `_nuxt` 文件仍在站点目录，未被引用、不影响页面。按规矩不自行删除，需清理时给精确命令由用户执行。
- **项目仍非 Git 仓库**：未 `git init`、未提交、未推送；`deploy/sync.sh` 与 `AGENTS.md` 含服务器 IP 与站点路径，将来推公开仓前必须确认可见性。
- **品牌与域名**：「薅客 Token」是临时品牌，名称 / 商标 / 域名可用性未核验；云端沿用 V1 建立的 `bp.zzzmxxkj.com`。
- **云端只是演示预览**：纯静态托管，不代表 V2 后续的 Nitro Server API / PostgreSQL / Drizzle / Docker Compose 生产架构；切换需单独评审。
- **V2 迁移方案文档** §2 / §6 的旧盘点文字未改写（用户文档，未经许可不动），已在 `AGENTS.md` 标注以本文件为准。
- **`npm run generate` 的 Nitro cache-driver WARN** 未消除，产物与云端页面均正常。
- 搜索 / 筛选 / 排序、投稿、有效性反馈、审核后台、官方来源采集、真实数据库、认证与服务端 API **均未实现**；搜索框是明确 `aria-disabled` 的「即将开放」占位。

## 下一轮（须用户确认）

按 V2 §1 产品范围逐轮推进：搜索 / 筛选 / 排序 → 用户投稿与有效性反馈 → 管理员审核与官方来源采集 → 真实数据库与服务端 API。每轮单独确认，本轮一律不动。

## 变更记录

- 2026-11-13 13:36：V1 第一轮开工（工程骨架 + 首页），13:57 完成并部署，记录见本文件历史版本与 `2026-11-13.md` §第一轮收口。
- 2026-11-13 13:59：用户插入 V2 方案，暂停 V1 后续工作等待迁移方式确认。
- 2026-11-13 14:03：用户决定「按 V2 做、V1 舍弃」，V2 第一轮开工。
- 2026-11-13 14:19：V2 第一轮完成，填入全部真实命令输出、云端验证结果与遗留风险；停在本轮等待用户确认。

## P03 开工记录（2026-11-14）

- 已新增 `kanwanliao/app/ForumReadRepository.php`，初步实现公开帖子列表查询；尚未连接路由或页面。
- 本机 PHP 8.2 执行该文件语法检查通过；既有 `tests/run.php` 为 33 passed、0 failed，但这些测试不覆盖新增查询。
- ~~尚未进行真实数据库查询、剧透泄露集成测试、浏览器走查和部署；P03 未完成、未验收。~~ → 以上为**开工时点（2026-11-14 早段）状态**，后续已全部补齐，见下节「P03 完成记录」。
- 2026-11-14：只读查询将剧透标题替换为固定提示，并排除剧透标题和正文的搜索匹配；新增 `tests/forum_repository.php`，使用 PDO 替身检查 SQL 结构与绑定，6 项通过。该测试不执行真实 SQL，不能代替 MySQL 集成验证。

## P03 完成记录（2026-11-14 01:07–01:15）

> **定位**：P03 只读论坛从「只有查询类」推进到**完整可用并已上线**，并补齐文档状态。**没有新增账号、发帖、评论、审核功能**（属 P04–P05）。

### 交付内容

| 项 | 结果 |
|---|---|
| 路由（`config/routes.php`，7 条 GET） | `/`、`/category/{slug}`、`/titles`、`/title/{id}`、`/thread/{id}`、`/search`、`/health` |
| 控制器 | `HomeController`、`CategoryController`、`TitleController`、`ThreadController`、`SearchController`、`HealthController`，公共基类 `ForumController` |
| 数据访问 | `ForumReadRepository`（实现 `ForumReader` 接口），`ReaderUnavailable` 兜底；测试可注入内存仓储，不依赖数据库 |
| 视图 | `home` / `thread` / `title` / `titles` / `search` + `partials/thread_list`、`partials/pager` |
| 演示数据 | `database/seeds/demo_p03.sql`：5 部作品（含同名不同年份/类型）、3 个**不可登录**演示作者、9 帖（含 2 剧透、1 hidden、1 pending）、4 条评论 |

### 真跑结果

| 检查 | 真实结果 |
|---|---|
| `php tests/run.php`（本机 PHP 8.2） | **70 passed, 0 failed** |
| 剧透防护 | 10 个页面默认**无正文泄露**；按剧透正文关键词**搜不到**剧透帖；`hidden`/`pending` 帖直访 **404**；分享 meta 描述不含正文 |
| 可访问性（WCAG AA） | 浅色 **252 处文本 / 0 失败**，深色 **252 处文本 / 0 失败**（5 个页面各测一遍） |
| 线上回归（部署后） | `/` 200·8653B、`/titles` 200·5508B、`/thread/2` 200·4969B、`/title/2` 200·4570B、`/category/movie` 200·5510B、`/search?q=流浪` 200·4440B |
| `/health` | `{"app":"ok","env":"production","timezone":"UTC","database":{"status":"ok"},"stage":"P03"}` |

### 期间修复的真实缺陷

1. **深色主题对比度不达 AA（3 类）**：`--accent` 在深色下是浅蓝 `#93c5fd`，却配白字（**1.8:1**）；深色 `--muted` 在标签底上 **4.06:1**；浅色 `--muted` **4.39:1**。已引入语义色对 `--on-accent`（浅 `#ffffff` / 深 `#0f172a`），两套 `--muted` 调为 `#5f6876` / `#aab2be`。修复后深色 **9.90:1**、浅色 **5.17:1**，全部达 AA。
2. **`/health` 的 `stage` 与实际不符**：仍写 `P01/P02`，已改为 `P03` 并部署验证。

### 排查结论（非缺陷，避免重复怀疑）

- **`/title/1` 返回 404 属正常**：线上作品表实际 id 为 **2–6**（自增从 2 起），不存在 id=1。只读查询确认：`2 风起小城(series)`、`3 雨后的车站(movie)`、`4 长夜灯火(series)`、`5 长夜灯火(movie)`、`6 未完成的夏天(movie)`。
- **审计脚本 `checked: 10` 是假通过**：本机 Chrome 不读 `https_proxy` 环境变量，必须显式传 `--proxy-server=http://127.0.0.1:9776`，否则渲染 Chrome 错误页。三个审计工具已内置，`probe.js` 可判别（`location.href === 'chrome-error://chromewebdata/'`）。

### 未完成 / 遗留

- ~~**键盘 Tab 焦点路径走查未做**（P03 验收项之一）~~ → **2026-11-14 01:55 已补**，见 §P06 完成记录。
- 品牌名称「看完聊」为工作名，**待用户确认**。
- 独立审计 Agent 未派出；用户签收未完成。
- 项目仍**不是 Git 仓库**；~~P04–P06 未开工~~ → **P04–P06 已于 2026-11-14 完成**。
- MySQL 5.7.44 已 EOL 且与 20+ 生产站点同实例，正式上线前须评估独立实例。

## P04 / P05 完成记录（2026-11-14 01:18–01:45）

> **定位**：从只读论坛推进到可注册、可发帖、可评论、可举报、可审核的完整闭环。**没有引入框架或新依赖**，仍是 PHP 8.2 + PDO + 原生视图。

### 新增代码

| 层 | 文件 |
|---|---|
| 迁移 | `database/migrations/002_auth_and_safety.sql` / `.down.sql`（`rate_limits` 表 + `comments.status` 索引 + `reports` 目标索引） |
| 会话与安全 | `SessionStore` / `NativeSession` / `MemorySession` / `Auth` / `Csrf` / `RateLimiter` / `PdoRateLimiter` |
| 数据 | `AccountStore` / `UserRepository` / `ContentWriter` / `ContentRepository`；`ForumReader` 增加 `userProfile`、`threadsByAuthor`、`countThreadsByAuthor` |
| 校验 | `Validation`（用户名/邮箱/密码/标题/正文/评论/举报原因） |
| 控制器 | `AuthController`、`PublishController`、`CommentController`、`ReportController`、`UserController`、`AdminController` |
| 视图 | `login`、`register`、`publish`、`user`、`admin`；`thread` 增加评论框与举报入口；`layout` 增加登录态导航与提示条 |
| 路由 | 7 条 GET + 9 条 POST，见 `config/routes.php` |

### 关键设计决定

- **先发布后审核 + 受限账号前置审核**：`active` 账号内容直接 `visible`；`restricted` 账号内容进 `pending`；`disabled` 不能登录。理由：冷启动需要「有人愿意发第一帖」，同时保留滥用控制手段。
- **会话只存 id**：角色与状态每请求读库，降权/停用立即生效，不依赖过期会话。
- **剧透在 SQL 层拦截**：未展开时 `body` 列为 `NULL`，视图拿不到原文。
- **限流键哈希**：`rate_limits` 只存 `bucket + SHA256(地址|账号)`，不落明文 IP。

### 真跑结果

| 检查 | 结果 |
|---|---|
| 本机 `tests/run.php` | **121 passed, 0 failed**（新增 51 项覆盖注册/登录/限流/发帖/评论/举报/权限/审计） |
| 迁移 `002` | 线上 `up` → `down` → 再 `up` 全部成功；结构核对：`rate_limits` 4 列 + 唯一键、`comments.idx_comments_status`、`reports.idx_reports_target` 均存在 |
| 注册（中文用户名） | **303 → /**；导航出现「验收用户A1」与「退出」 |
| 未带 CSRF 注册 | **422**，且未创建账号 |
| 发帖（剧透） | **303 → /thread/11**；默认页 0 次正文、含「我确认，展开剧透」；`?reveal=1` 后正文出现 |
| 列表/搜索/作品页 | 剧透正文出现次数均为 **0** |
| 评论 | **303 → /thread/11#comments**，刷新后可见 |
| 举报 | **303 → /thread/11**，后台队列可见 |
| 权限 | 普通用户 `/admin` **403**；未登录 **303 → /login** |
| 审核闭环 | 隐藏被举报内容 → 帖子页 **404**、首页消失；待审帖 404 → 后台通过 → **200** |
| 审计 | `report.hide_target`、`report.resolve` 等记录真实落库（含操作者、对象、上下文） |
| 限流 | 连续注册触发 **429**（线上实测） |

## P06 完成记录（2026-11-14 01:45–02:00）

### 交付

| 项 | 结果 |
|---|---|
| 安全响应头 | CSP（`default-src 'self'`，无 `unsafe-inline`）、`X-Frame-Options: DENY`、`nosniff`、`Referrer-Policy`、`Permissions-Policy`、HTTPS 下 HSTS |
| 会话加固 | `HttpOnly` + `Secure` + `SameSite=Lax` + `use_strict_mode` + 登录/退出换 id |
| 备份 | `scripts/backup.sh`：`--single-transaction`，产物 `600`，`gzip -t` 校验，保留 14 份，默认拒绝非隔离库 |
| 恢复 | `scripts/restore.sh`：目标必须 `_dev`/`_test`、必须 ≠ 运行库、必须 `--yes`，不建库不提权 |
| 恢复演练 | `scripts/backup-drill.sh`：8 张表行数全部一致，克隆表自动清理 |
| 可访问性审计 | `.workbuddy/tools/p06-audit.js`（CSP 违规 / 横向溢出 / 键盘焦点 / 表单标签 / 对比度） |
| 运维文档 | `docs/影视论坛-安全与运维手册.md` |

### 真跑结果

| 检查 | 结果 |
|---|---|
| 安全头 | 6 项均在线上响应中出现；CSP **0 违规** |
| 备份 | `kanwanliao_dev-20261113-174651.sql.gz`，4471 字节，8 表，SHA256 `2d992872…56e4`，权限 `-rw------- www www` |
| 恢复演练 | users 5/5、titles 5/5、threads 11/11、comments 5/5、reports 1/1、admin_audit_logs 2/2、rate_limits 6/6、schema_migrations 2/2，**全部一致**；演练后无 `bkdrill_%` 残留 |
| 可访问性 | 7 页 × 桌面 1440 / 移动 375 × 浅色/深色 = 28 次加载；对比度 **798 处 / 0 失败**；横向溢出 **0**；无标签控件 **0**；键盘 12 步内焦点不可见 **0** |
| 部署后回归 | 首页 / 作品 / 帖子 / 搜索 / `health` 均 200，`stage` 已更新为 `P06` |

### 工具踩坑（会再犯）

- **必须校验导航真的到达目标站点**：本机 Chrome 冷启动时前几秒导航会落到 `chrome-error://chromewebdata/`，错误页同样能跑 DOM 统计，会产出「0 溢出 0 违规」的**假通过**。工具已内置 `location.host` 断言，并在未到达时直接判失败。
- **本机代理已不是必需品**：2026-11-14 实测本地代理 `127.0.0.1:9776` 未监听、直连返回 200。审计工具改为代理可选（`KWL_PROXY`），不再强制走代理。
- **Git Bash 会把 `/publish` 这类参数当成本地路径转换**，导致审计脚本访问到 `E:/.../publish`。传路径参数时需 `MSYS_NO_PATHCONV=1`。
- **curl.exe 会把中文参数按 GBK 发送**（`%D1%E9…`），线上验收中文输入时必须用文件传 UTF-8：`--data-urlencode "name@<windows路径>"`。

### 未完成 / 遗留

- **跨浏览器未实测**：仅 Chrome；Firefox / Safari / 微信内置浏览器待补。
- **「新建独立库再恢复」未演练**：本项目账号无建库权限（实测 1044），需管理员建库授权后执行。
- **业务合规待业主确认**：用户协议、隐私政策、素材许可、备案资质、是否开放注册、找回密码。
- 独立审计 Agent 未派出；用户签收未完成；品牌名待确认；项目仍不是 Git 仓库。
