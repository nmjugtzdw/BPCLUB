# BaiPiao-Community 项目记忆（看完聊 · 影视讨论论坛）

> Last updated: 2026-11-14 02:20
> 只放「换个会话还会再犯」的硬结论。流水看 `2026-11-13.md` / `2026-11-14.md`，进度真源看 `TASKS.md`，协作约定看项目根 `AGENTS.md`，需求真源看 `docs/影视论坛-*.md`。

## 索引表

| 要查什么 | 结论在本文件 | 细节在哪天哪段 |
|---|---|---|
| 产品方向、V1/V2/V3 为何废弃、当前阶段 | §1 | `2026-11-13.md` §计划变更、§V2 第一轮、§影视论坛 P00 |
| **怎么部署到宝塔（用户已授权）** | §2 | `AGENTS.md` §3.1、`2026-11-13.md` §P01 云端部署 |
| **数据库怎么连、怎么跑迁移、时区陷阱** | §2 | `AGENTS.md` §3.2、`2026-11-13.md` §P02 数据库闭环 |
| 域名、站点与服务器环境约束 | §2 | `2026-11-13.md` §部署决定、§同步踩坑、§影视论坛 P00 |
| **本机 Chrome / 审计工具怎么用（代理可选 + 必须断言到达）** | §2 | `2026-11-14.md` §深色主题、§P04/P05/P06 |
| 账号权限、限流阈值、审核策略、备份恢复 | §4 | `docs/影视论坛-安全与运维手册.md`、`2026-11-14.md` §P04/P05/P06 |
| 旧代码隔离、为什么不能自己删 | §3 | `2026-11-13.md` §影视论坛 P00 |
| 技术路线与 P00–P06 实现细节 | §4 | `2026-11-13.md` §影视论坛 P00、§P01/P02；`2026-11-14.md` |
| 协作、文件所有权、状态真源 | §5 | `AGENTS.md` §5、`D:\workspace\规矩文档.md` |
| 对外文案口径 | §6 | 用户级 `MEMORY.md`（对外文案铁律） |

## 1. 项目状态

2026-11-13 22:15 用户第三次改方向：V1（综合福利论坛）、V2（薅客 Token）、V3（工具研究所）**产品方向全部舍弃**，改做**电影 / 电视剧影评讨论论坛**，域名 `kwl.zzzmxxkj.com`；工作名「看完聊」（取自原型与域名缩写），**品牌名称仍待用户确认**。

2026-11-13 22:30 用户确认技术路线（PHP 8.2 + MySQL），新工程 `kanwanliao/` 落地，**P01 + P02 已开工**。**23:10 云端已上线**：`https://kwl.zzzmxxkj.com/` 不再是宝塔默认页，跑的是本项目首页。**23:00 数据库已打通**：隔离库 `kanwanliao_dev` + 专用非 root 账号，迁移 up / down / 重建全部实测通过，`/health` 的 `database` 已从 `not_configured` 变 `ok`（PHP-FPM 真实连库）。

**2026-11-14 03:00 状态：P00–P06 全部完成并上线，功能闭环可用，UI 已按「散场以后」报纸式设计稿重做并部署**（注册/登录/退出、发帖、评论、举报、用户主页、管理后台审核与处置）。`tests/run.php` **129/129**。权限边界（未登录 303 / 普通用户 403 / 管理员 200）、CSRF、限流、剧透防护、审计日志均已线上实测；备份与恢复演练通过；线上 UI 复测 5 页 × 桌面 1440 / 移动 375 × 浅深主题共 10 个用例，**493 处文本对比度 0 失败**、溢出 0、CSP 0、无标签 0、焦点不可见 0、缺 alt 0。`/health` 的 `stage` 为 `P06`。**未完成**：独立审计、用户签收、品牌名确认、跨浏览器（Firefox/Safari/微信）实测、业务合规（用户协议/隐私/素材许可/备案）、找回密码。

**审核策略已于 2026-11-14 03:15 改为「全部先审后发」**（`PublishController` / `CommentController` 硬编码 `$status = 'pending'`，不再按 `isRestricted()` 区分）。旧策略「先发布后审核（active → visible）」**已废弃**，别再按它判断行为或写文案。直接后果：**任何新帖、新评论都不会立刻公开，必须有管理员在后台放行**；线上必须有可用 admin 账号，否则内容永久卡在待审。站内**没有隐藏的默认管理员**，需要时按运维手册 §11 用一条 SQL 给已注册账号授权。2026-11-14 03:17 线上已存在管理账号 `dl20052006`（id 8，`role=admin` `status=active`）。

需求真源是 `docs/影视论坛-项目白皮书与总体方案.md`、`分阶段实施与任务清单.md`、`审计与验收方案.md`、`P00-现状核查与隔离方案.md`、`影视论坛-效果图说明.md`；`docs/` 下 V1/V2/V3 文档只作历史追溯，**不作需求依据**。旧 V2 的 42 条单测、旧静态部署、旧云端页面**一律不能当影视论坛验收证据**。

## 2. 部署拓扑与服务器硬约束

**部署已获用户授权（2026-11-13）**：用户明确「告诉其他 agent 可以上传到宝塔」。**一条命令**在项目根执行：`bash kanwanliao/scripts/deploy.sh`（tar + SSH 管道，覆盖式不删除，自动排除 `.runtime/`、`.env`、`storage/` 日志缓存，`chown` 用 `find ! -name '.user.ini' ! -name '.env'`）。上传代码 ≠ 可以发布未完成功能，新增功能上线仍须用户确认。

- 源码唯一真源 `D:\workspace\BaiPiao-Community`；服务器 SSH `ubuntu@111.231.174.142` 免密 + `sudo -n`；宝塔面板 8888 从本机 TIMEOUT，别向用户要面板密码。
- **站点必须一直满足三件事**：运行目录 `/www/wwwroot/kwl.zzzmxxkj.com/public`；PHP **8.2**；伪静态 `try_files $uri $uri/ /index.php?$query_string;` 写在 `/www/server/panel/vhost/rewrite/kwl.zzzmxxkj.com.conf`。三者已于 23:10 配好，正常不用动。
- 线上 `.env` 在站点根（`www:www` `600`）：`APP_ENV=production` / `APP_DEBUG=false` / `TIMEZONE=UTC` / DB 全空。**部署脚本永不覆盖它**。
- 新站证书 `CN=kwl.zzzmxxkj.com`（2026-11-13→2027-02-11），**http 301 强制 https**，所有验证必须走 https（用 http 测会拿到 301 误判失败）。
- **旧站 `bp.zzzmxxkj.com` 目录与 vhost 均已不存在**，不要再往那个域名传东西。
- 服务器：nginx 1.30.6；PHP CLI 默认 **7.0.33**（跑本项目会 Parse error，必须用 `/www/server/php/82/bin/php`）；宝塔装 70/74/**82**；MySQL **5.7.44**（已 EOL，不得为本项目升级共享实例）；Node 18.19.1；磁盘 36G 可用；`/www/wwwroot` 下 20 多个站点目录，**绝不碰其它站点**。
- 本机：Node 22.22.2 / npm 10.9.7，PHP 用便携版 `kanwanliao/.runtime/php82/php.exe`；**本机无 MySQL**；**项目已是 Git 仓库**（`main...origin/main`，唯一提交 `300f91c =feat-initial-P00-P06`，工作区有未提交改动）。文档里「仍不是 Git 仓库」的说法已过时，别再照抄。
- **本机 Chrome 访问 `*.zzzmxxkj.com` 目前直连即可**（2026-11-14 01:40 实测：`netstat` 无 9776 监听、`curl -x 127.0.0.1:9776` 返回 000、直连 200）。代理 `127.0.0.1:9776` 曾经是必需的，现在**只在需要时**用环境变量 `KWL_PROXY=http://127.0.0.1:9776` 打开；四个工具（`probe.js` / `cdp-check.js` / `contrast-audit.js` / `p06-audit.js`）已改为「有 `KWL_PROXY` 才加 `--proxy-server`」。注意环境变量 `https_proxy` 对 Chrome 无效。
  - **教训（2026-11-14 02:57 又踩一次）**：`cdp-check.js` 曾漏改、仍硬编码 9776，截图落到 Chrome 错误页，还在错误页上统计出「2 张图片缺 alt」的假结论。**改这类工具必须四个一起改，并且改完真要跑一次看 `location.host`。**
- **★ 审计/对比度脚本必须断言「真的到达了目标站点」**：Chrome 冷启动前几秒导航会落到 `chrome-error://chromewebdata/`，错误页照样能跑 DOM 统计，于是产出 `checked: 10 + failures: []` / `溢出 0 违规 0` 的**假通过**（本轮已两次踩到）。判据是 `location.host === 'kwl.zzzmxxkj.com'`，未到达就 `throw` 作废本次结果，并在报告里输出 `reachable` / `navAttempts` / `onOrigin`。`goto()` 内要重试导航 5 次并 `sleep` 后复查 `location.href`，不能只发一次 `Page.navigate` 就当到达。
- 新站 `.user.ini` 带 immutable `i` 属性，`chown -R` 会 `Operation not permitted`；不手写 vhost 主配置（面板重建会覆盖，改前备份 `.bak-<时间戳>`）；宝塔 nginx 不走 systemd，**不用 `systemctl reload nginx`**，用 `sudo /www/server/nginx/sbin/nginx -s reload`。
- 服务器 MySQL root 密码已于 2026-11-13 由用户提供并实测可连（MySQL 5.7.44，utf8mb4）。**已建隔离库 `kanwanliao_dev` + 专用账号 `kanwanliao_dev`@127.0.0.1/localhost，非 root，权限仅本库**；实测该账号看不到其它库、读 `mysql.user` 报 1142、建库报 1044。**密码只落服务器 `.env`（`www:www` `600`），绝不写进仓库任何文件或文档。** 宝塔「数据库」页也能看到该库。
- **CLI 时区陷阱**：`scripts/migrate.php` 不经过 `Bootstrap`，服务器 `php.ini` 时区是 `PRC`，已在该脚本显式 `date_default_timezone_set('UTC')`。**以后新写任何 CLI 脚本都必须自己设 UTC**，否则写库时间差 8 小时。已验证 PHP 时间与 `UTC_TIMESTAMP()` 同秒。
- **服务器 `.env` 的数据库密码键名是 `DB_PASS`，不是 `DB_PASSWORD`**（键名共 10 个：`APP_ENV`/`APP_DEBUG`/`TIMEZONE`/`LOG_PATH`/`DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS`/`DB_CHARSET`）。写错变量会让 `mysql` 转成交互式 `Enter password:`，SSH 命令**静默卡死到 timeout**（现象是 stdout 空、exit 1 或 SIGTERM），极易误判成网络/SSH 故障。查库脚本务必 `set -a; . ./.env; set +a` 后用 `"$DB_PASS"`。
- **`/title/1` 返回 404 不是缺陷**：线上 `titles` 表实际 id 为 **2–6**（自增从 2 起），不存在 id=1。别当 bug 反复排查。
- 迁移三道门禁（`DB_NAME` 须 `_test`/`_dev` 结尾、`DB_USER` 不得 root、配置不全拒绝）实测真实 exit 1，**不要为跑通而放宽**。跑迁移用 `sudo -u www /www/server/php/82/bin/php scripts/migrate.php status|up|down`。
- 服务器噪音可忽略：`pdo_oci.so` 加载失败、`mbstring already loaded`、tar 时间戳「in the future」、`sudo -u www` 跑测试报 `storage/logs/` Permission denied（权限非代码缺陷）。

## 3. 旧代码处置

用户明确授权「v1 和 v2 不需要的代码文件都可以删了」。但 `D:\workspace\规矩文档.md` §3.9 禁止 Agent 执行任何删除，且明确「不要换写法绕过」——**不删、不用 `.NET File.Delete` / `del` 替代**，只能移到 `D:\workspace\_待删\` 由用户统一清。

已于 22:20 把 **25 项**整体 `renameSync` 到 `D:\workspace\_待删\BaiPiao-Community-V1V2-20261113\`（V2 十个源码目录、`legacy/`、`deploy/`、Nuxt 配置、`.nuxt`/`.output`/`node_modules`/`dist`、旧 README）。`dist` 是指向 `.output/public` 的**断链符号链接**，`existsSync` 为 false，要用 `lstatSync` 才能移走。隔离区 `README.md` 有清单、恢复方式与用户可执行的 `rd /s /q` 命令；旧 V2 README 改名 `README.v2.md`。

**保留**：`docs/`（全部）、`prototypes/kanwanliao/`、`.workbuddy/`、`AGENTS.md`、`TASKS.md`。任何 Agent 不得从待删区复制旧代码继续开发。

## 4. 技术路线：已确认 PHP 8.2 + MySQL

2026-11-13 22:30 用户选择 **PHP 8.x + PDO + MySQL/MariaDB + Nginx/PHP-FPM + 原生 HTML/CSS/JS 模块化单体**，并同意准备本地 PHP 环境。**不要再重启技术路线讨论，不要改回 Nuxt。**

工程在 `kanwanliao/`：`public/` 唯一 Web 根；配置 / 迁移 / 日志 / 测试 / 备份在根之外。本机原本无 PHP / MySQL / Docker，已下载 **PHP 8.2.35 NTS x64** 便携版到 `kanwanliao/.runtime/php82/`（`.gitignore` 排除），写 `php.ini` 启用 `pdo_mysql` / `mbstring` / `openssl` / `fileinfo` / `curl`，时区 UTC。**本机至今无 MySQL 实例**：所有涉及真库的验证（迁移、种子、账号、审计）一律在服务器隔离库 `kanwanliao_dev` 上跑，本机只跑 `tests/run.php`（内存替身）。

真跑证据：本机 `php -l` 21 文件 0 错误；`tests/run.php` **33/33**；内置 server 冒烟 **10/10**（含 Web 根之外 `/app/Config.php`、`/.env` 均 404）。服务器侧 `php -l` **18 文件 0 错误**（用 8.2 绝对路径）；部署后外网首页 200、`/health` 200 `production`、未知路径 404、敏感路径 404。**P02 迁移 / 回滚 / 重建已在 `kanwanliao_dev` 实测通过**（2026-11-13 23:00）：7 张表、7 个外键、索引齐全、唯一键与外键 RESTRICT 实跑拒绝、中文回读无乱码、`down` 后只剩 `schema_migrations`、`up` 可重建、重复执行幂等；`/health` 的 `database` 已为 `ok`。

**P03 只读论坛已实现并上线（2026-11-13 23:10–2026-11-14 00:58）**：`Category` / `Pager` / `ForumReader`（接口）/ `ReaderUnavailable` / 4 个 Controller + 基类 `ForumController`；视图 `home` / `thread` / `title` / `titles` / `search` + `partials/thread_list`、`partials/pager`；种子 `database/seeds/demo_p03.sql`（5 部作品、3 个不可登录演示作者、9 帖含 2 剧透 1 hidden 1 pending、4 评论）。**测试 70/70 通过**（内存仓储注入，不依赖数据库）。剧透防护已实测：10 个页面默认无正文泄露、关键字搜不到剧透帖、hidden/pending 直访 404。**可访问性：浅色 / 深色各 5 个页面、各 252 处文本，WCAG AA 全部通过**。

**P04 / P05 / P06 已实现并上线（2026-11-14 01:00–02:05），`tests/run.php` 到 122/122**：

- **会话只存 `uid`，角色与状态每请求读库**。这是本轮最重要的设计决定：`Auth::isAdmin()` / `isRestricted()` 都现查 `users` 表，所以后台降权、封号**立即生效**，不必等会话过期。`disabled` 账号在 `Auth::user()` 里直接清会话。登录/退出都 `session_regenerate_id(true)`。
- **CSRF 双提交**：会话级 64 位十六进制令牌 + `hash_equals`；失败返回 **422 且不写库**。所有 POST 都走 `rejectCsrf()`。
- **限流键不存明文 IP**：`SHA256(bucket|地址|账号)` 存 `rate_limits`，`UNIQUE(bucket,key_hash,window_start)` + `ON DUPLICATE KEY UPDATE counter = counter + 1` 原子计数。**测试时把限额打满会真的 429**，别误判成缺陷——查 `rate_limits` 清理对应 bucket 即可。
- **剧透在 SQL 层拦截**：未展开时 `body` 列直接返回 `NULL`，搜索排除剧透帖，`meta description` 只出提示文本。不是靠前端折叠（原型里的 `details` 不满足服务端门禁）。
- **接口 + PDO 实现 + 内存替身**是三层模式的固定写法：`ForumReader` / `AccountStore` / `ContentWriter` / `RateLimiter` 各有 PDO 实现与测试替身。**新增测试替身前必须在 `tests/run.php` 顶部显式 `require` 接口文件**，否则自动加载器还没注册就报 `Interface "App\AccountStore" not found` / `Class FakeReader contains 3 abstract methods`。
- **`migrate.php` 已支持多版本**：按 `database/migrations/*.sql` 文件名发现版本，`down [version]` 会连带回滚其后所有版本；`002_auth_and_safety.sql` 已线上实测 `up → down → up` 三次全通过。
- **备份/恢复脚本带门禁**：`backup.sh` 只接受 `_test`/`_dev` 结尾的库（对生产须显式 `--allow-prod`），`gzip -t` 校验、目录 700 / 文件 600、`KEEP=14`；`restore.sh` 目标必须 `_dev`/`_test` 且 ≠ 运行库且必须 `--yes`。**演练用 `backup-drill.sh`：同库 `CREATE TABLE ... LIKE` 克隆表（不含外键）→ 表名替换导入 → 逐表比对行数 → 清理**，实测 8 表行数全一致、无 `bkdrill_%` 残留。
- **验收用的临时管理员账号必须清理干净**，顺序是 audit → comments → reports → threads → users → rate_limits（外键顺序），否则公网站点会留下已知密码的账号。清理后站点应回到演示种子状态（3 示例作者 / 9 帖 / 4 评论）。

## 5. 协作与文件所有权

详见 `AGENTS.md` §5、§7。要点：`docs/影视论坛-*.md` 是用户文档未经许可不改写；`prototypes/kanwanliao/` 只是静态视觉参考，其 `details` 折叠**不满足服务端剧透防泄露门禁**；剧透必须在服务端默认不输出正文，列表/标题/摘要/搜索/分享 meta/无 JS 逐项核对；不编造用户、评论、评分、活跃度，演示数据醒目标注。

同一文件多处编辑**串行一次一个**；不同文件可并行；动手前查 Git 状态与文件时间戳（本轮 `AGENTS.md`/`docs`/`TASKS.md` 在 21:39–22:06 被其它 Agent 并发改过）。改代码后按 `D:\workspace\规矩文档.md` §6 往 `D:\workspace\修改记录.md` 登记（多文件各列一条）。不自动 `git push`、不自动部署、不自动写 `踩坑日志.md`（收尾给候选，用户同意才写）。文件 UTF-8 无 BOM、`.sh` 用 LF、不写 `.bat`/`.cmd`。

## 6. 对外文案

站内可见文案、README、发布说明只写读者视角。**当前实际状态**：站点是可注册、可发帖评论的筹备/演示版本，内容为示例，不提供播放或资源下载。写文案时不要再说「暂不提供账号与发布」——账号与发布已经可用；但也不要把「筹备/演示」拿掉，因为内容仍是演示种子、业务合规（用户协议、隐私政策、备案）尚未确认。

**不写** Nuxt / PHP / Mock / Repository / UTC / 构建 / 类名 / 文件路径 / CSP / 限流阈值 / CSRF 这类技术术语。技术细节只进 `AGENTS.md`、`TASKS.md`、`docs/`、提交信息与 `修改记录.md`。判断标准见用户级 `MEMORY.md`「对外文案铁律」。
