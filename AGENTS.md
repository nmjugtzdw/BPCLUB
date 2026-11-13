# 看完聊（影视讨论论坛）· 多 Agent 共享协作约定

> **本文面向所有参与本项目的 Agent（不同工具、不同会话、不同角色），开工前先读完。**
> 2026-11-13 用户决定：V1（综合福利论坛）、V2（薅客 Token）、V3（工具研究所）产品方向**全部舍弃**，改做电影 / 电视剧影评讨论论坛；域名改为 `kwl.zzzmxxkj.com`。旧代码已按用户授权隔离到 `D:\workspace\_待删\`。
> **技术路线仍待用户确认（P00 门禁），本文不把任何技术栈写成既定决定。**
>
> | 内容 | 真源 |
> |---|---|
> | 工作区规矩（路径、编码、禁令、修改记录） | `D:\workspace\规矩文档.md` — **冲突时以它为准** |
> | 产品范围与安全边界 | `docs/影视论坛-项目白皮书与总体方案.md`（用户文档，不擅改） |
> | 阶段划分与验收条件 | `docs/影视论坛-分阶段实施与任务清单.md` |
> | 独立审计门禁 | `docs/影视论坛-审计与验收方案.md` |
> | P00 现状盘点与隔离建议 | `docs/P00-现状核查与隔离方案.md` |
> | 视觉参考 | `docs/影视论坛-效果图说明.md`、`prototypes/kanwanliao/` |
> | **当前进度与真实测试结果** | `TASKS.md` |
> | 面向读者的说明 | `README.md` |
> | 本文件 | 只写协作约定，不写进度、不复制规矩全文 |
>
> `docs/` 下 V1/V2/V3 历史文档只作追溯，**不是需求依据**。维护：协调者 Agent · 修改本文需在 `TASKS.md` 留痕并按规矩登记 `D:\workspace\修改记录.md`。

---

## 0. 一句话现状

技术路线已于 2026-11-13 22:30 由用户确认（PHP 8.2 + MySQL），新工程落在 `kanwanliao/`，**P01（原型与内容契约）+ P02（基础架构与数据库）已开工**。

**云端已于 2026-11-13 23:10 上线可访问**：`https://kwl.zzzmxxkj.com/` 不再是宝塔默认页，已跑通「本机同步 → 服务器运行」全链路，首页是**演示态论坛首页**（虚构示例帖、无账号无发帖）。用户已明确授权 Agent 直接上传到宝塔，命令与红线见 §3.1。

**数据库已于 2026-11-13 23:00 打通**：隔离库 `kanwanliao_dev` + 专用账号（非 root），迁移 up / down / 重建全部实测通过，`/health` 的 `database` 已从 `not_configured` 变为 `ok`。但**没有任何业务读写代码**——7 张表是空结构，首页仍用硬编码示例数据。连接方式见 §3.2。

工作名「看完聊」取自静态原型与域名缩写，**品牌名称待用户确认**，不得当作已定品牌对外宣传。

## 1. 需求基线与轮次边界

- 产品：中文电影 / 电视剧讨论论坛，看完聊感受、讨论剧情、求推荐；首页以帖子列表为中心，参考 NodeSeek 的紧凑信息密度，但品牌、文案、组件、代码自主设计。
- 首版范围：首页（最新 / 热门 / 四类入口）、作品资料页、帖子与评论、搜索、注册登录、剧透折叠、举报与管理员审核、响应式与深浅主题。详见白皮书 §3、§4。
- **明确不做**：影片 / 音频托管、在线播放、盗版资源链接、自动抓取影评与海报、大型影片库、付费会员、复杂推荐、私信、积分等级、移动 App；首版默认不开放图片上传。
- **内容真实性**：首批话题由真实用户 / 编辑提供，**禁止编造用户、评论、评分、活跃度**；测试数据必须醒目标注为演示。
- **剧透是硬门禁**：发帖与评论可标记剧透；列表、标题、摘要、搜索结果、分享摘要、无 JS 展示、服务端返回**均不得泄露剧透正文**；默认折叠、用户主动展开。单纯把原文写进 HTML 再用 CSS 隐藏**不算通过**。

| 阶段 | 范围 | 状态 |
|---|---|---|
| P00 | 方向确认与现状基线 | 环境核查完成、**技术路线已确认**、旧代码已隔离；独立审计与用户签收仍待完成 |
| P01 | 产品原型与内容契约 | **已开工**：首页原型、内容契约、剧透规则真源与测试；**已部署到云端供用户实测**，视觉与交互待用户确认 |
| P02 | 本地基础架构与数据库 | **已完成并通过**：入口 / 路由 / 配置 / 错误 / 日志 / PDO / 迁移脚本；隔离库 `kanwanliao_dev` 上迁移 up / down / 重建全部实测通过，外键与索引已结构级核验，安全门禁实测 exit 1 |
| P03–P06 | 只读论坛 / 账号发帖 / 管理审核 / 安全上线 | 均未开工 |

**每个阶段先获得用户明确开工确认，通过审计后等用户验收，不自动进入下一阶段。** 计划条目不等于已实现，测试与签收一律以真实证据为准。

## 2. 技术路线：已确认（2026-11-13 22:30）

用户明确选择 **PHP 8.x + PDO + MySQL/MariaDB + Nginx/PHP-FPM + 原生 HTML/CSS/JS 模块化单体**（白皮书 §6 / P00 §3 的建议方案），并同意为本机准备本地 PHP 运行环境。新工程目录 `kanwanliao/`，`public/` 是唯一 Web 根目录；配置、迁移、日志、测试、备份都在 Web 根之外。**不要再重启技术路线讨论，也不要改回 Nuxt。**

2026-11-13 22:15 实测环境（只读，供决策，不是决定）；标「已变」的是 23:10 部署后的现状：

| 项 | 实测结果 |
|---|---|
| 本机 | Node 22.22.2 / npm 10.9.7；系统无 PHP，已备便携版 `kanwanliao/.runtime/php82/php.exe`（8.2.35） |
| 本地 Git | `git status` → `fatal: not a git repository`；项目**仍不是 Git 仓库**，未 init / 未提交 / 未推送 |
| 服务器 | Ubuntu，nginx 1.30.6，SSH `ubuntu@111.231.174.142` 免密 + `sudo -n` |
| 服务器 PHP | CLI 默认 **7.0.33**（过旧，跑本项目会 Parse error）；宝塔另装 70 / 74 / **82**，其中 82 具备 `pdo_mysql`、`mbstring`、`openssl`，站点已切到 82 |
| 服务器数据库 | MySQL **5.7.44**（已 EOL，须评估安全维护状态；不得为本项目升级共享实例）；**已建隔离库 `kanwanliao_dev` + 专用账号**，仅限开发阶段，与 20 多个生产站点同实例 |
| 新站点 | 已变：`/www/wwwroot/kwl.zzzmxxkj.com` 运行目录已指向 `public/`，已启用 PHP 8.2，伪静态已配 `try_files`，页面为本项目首页（不再是默认页）；证书 2026-11-13 → 2027-02-11；http 301 强制 https |
| 旧站点 | `bp.zzzmxxkj.com` 目录与 vhost **均已不存在**（用户已换域名），旧静态预览已下线 |
| 磁盘 | 36G 可用；`/www/wwwroot` 下 20 多个站点目录 |

本机 PHP 已在 `kanwanliao/.runtime/php82/` 备好便携版 **8.2.35**（启用 `pdo_mysql` / `mbstring` / `openssl`，时区 UTC，已排除出版本库）；本机**仍无 MySQL 实例**，本机侧跑迁移需指向服务器开发库或自建实例。服务器 MySQL 5.7.44 已 EOL，上线前须评估独立实例，**不得为本项目升级共享数据库**。隔离库与迁移实测结果见 §3.2 与 `TASKS.md`。

## 3. 站点、部署与生产边界

```text
D:\workspace\BaiPiao-Community\kanwanliao\   ← 源码唯一真源（本地，所有 Agent 都改这里）
     └─ bash kanwanliao/scripts/deploy.sh     ← 唯一部署入口（tar + SSH 管道）
          └─ /www/wwwroot/kwl.zzzmxxkj.com/   ← 宝塔站点，已强制 HTTPS
               └─ public/                     ← 运行目录（vhost root 指向这里）
                    └─ https://kwl.zzzmxxkj.com/
```

| 项 | 值 |
|---|---|
| 云端入口 | `https://kwl.zzzmxxkj.com/`（http 301 → https） |
| 服务器 | `ubuntu@111.231.174.142`，SSH 免密 + `sudo -n` 可用 |
| 站点目录 | `/www/wwwroot/kwl.zzzmxxkj.com`，属主 `www:www` |
| 运行目录 | `/www/wwwroot/kwl.zzzmxxkj.com/public` |
| 服务器其它站点 | `/www/wwwroot` 下另有 20 多个生产站点，**一律不许触碰** |
| 宝塔面板 | 面板 Web UI（8888）从本机 TIMEOUT，操作走 SSH；不要向用户索要面板账号密码 |
| 旧部署脚本 | `deploy/sync.sh`（Nuxt 静态站用）已随旧代码隔离到 `_待删/`，**禁止用于本项目** |

### 3.1 部署（用户已授权 Agent 直接上传，2026-11-13）

**一条命令，在项目根 `D:\workspace\BaiPiao-Community` 下执行：**

```bash
bash kanwanliao/scripts/deploy.sh
```

脚本做的事：`tar czf -` 打包 `app config resources public scripts database tests docs README.md .gitignore .env.example` → SSH 管道 → 服务器端 `sudo tar xzf -` 解包到站点目录 → `find ... -exec chown www:www` 修正属主 → 自检 `/health`。**覆盖式，不删除服务器文件。**

**脚本已内置的排除项，不要移除：**

- `.runtime/`（本机 PHP 便携版 119MB）、`.env`（线上 `.env` 绝不能覆盖）、`storage/logs/*.log`、`storage/cache/*`、`storage/sessions/*`
- `chown` 用 `find ... ! -name '.user.ini' ! -name '.env'`，**不要改成 `chown -R`**：`.user.ini` 带 immutable 属性，`chown -R` 会报 `Operation not permitted` 让脚本非零退出（已踩过）

**站点侧必须一直满足的三件事**（已配好，正常情况不用动）：

| 项 | 值 | 说明 |
|---|---|---|
| 运行目录 | `/public` | vhost 的 `root` 行；面板「网站 → 设置 → 网站目录」改 |
| PHP 版本 | **8.2** | 面板「网站 → 设置 → PHP 版本」；vhost 引用 `enable-php-82.conf` |
| 伪静态 | `try_files $uri $uri/ /index.php?$query_string;` | 写在 `/www/server/panel/vhost/rewrite/kwl.zzzmxxkj.com.conf` |

**部署后自检（外网 HTTPS，站点强制 HTTPS 所以不能用 http 测）：**

```bash
curl -sS https://kwl.zzzmxxkj.com/health          # 应返回 JSON，stage 字段可见
curl -sS -o /dev/null -w '%{http_code}\n' https://kwl.zzzmxxkj.com/
```

服务器本地自检（绕过 DNS / CDN）：`curl -k -s --resolve kwl.zzzmxxkj.com:443:127.0.0.1 https://kwl.zzzmxxkj.com/health`

**部署相关的坑（都已踩过）：**

- 服务器系统 `php` 是 **7.0.33**，跑本项目会报 Parse error（构造器属性提升、箭头函数）。语法检查 / 跑测试必须用 `/www/server/php/82/bin/php`。
- `sudo -u www` 跑 `tests/run.php` 会因 `storage/logs/` 属主问题报 Permission denied，这是权限不是代码缺陷；要跑测试用 `ubuntu` 身份且先确认 `storage/` 可写。
- 服务器 PHP 加载 `pdo_oci.so` 失败、`mbstring already loaded` 是共享配置噪音，不影响本项目。
- `tar` 可能提示时间戳「in the future」，本机与服务器时钟差，无影响。
- 宝塔 nginx **不走 systemd**：不要 `systemctl reload nginx`；真要 reload 用 `sudo /www/server/nginx/sbin/nginx -s reload`。静态覆盖产物通常不需要 reload。

### 3.2 数据库（已打通，2026-11-13 23:00）

**连接信息只在服务器 `.env`，不在仓库里。** 线上 `.env` 位于 `/www/wwwroot/kwl.zzzmxxkj.com/.env`（`www:www` `600`），键为 `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` / `DB_CHARSET`。要看现值：

```bash
ssh ubuntu@111.231.174.142 "sudo sed -E 's/(DB_PASS=).*/\1***/' /www/wwwroot/kwl.zzzmxxkj.com/.env"
```

**隔离库与账号**：库 `kanwanliao_dev`（utf8mb4 / utf8mb4_unicode_ci），账号 `kanwanliao_dev`@`127.0.0.1` 与 `@localhost`，**不是 root**，权限仅限本库（全局只有 `USAGE`）。实测边界：看不到其它库、读 `mysql.user` 报 1142、`CREATE DATABASE` 报 1044。

**跑迁移**（`status` / `up` / `down`，均幂等）：

```bash
# 服务器上（脚本在 Web 根之外，不能从公网访问）
cd /www/wwwroot/kwl.zzzmxxkj.com && sudo -u www /www/server/php/82/bin/php scripts/migrate.php status
```

**三道安全门禁**（实测真实 exit 1，不是提示后继续）：`DB_NAME` 必须以 `_test` / `_dev` / `_dev_test` 结尾；`DB_USER` 不得为 `root`；配置不全直接拒绝。**不要为了跑通而放宽这三条。**

**必须知道的时区陷阱**：`scripts/migrate.php` 这类 CLI 脚本不经过 `Bootstrap::create()`，所以它自己显式 `date_default_timezone_set('UTC')`。服务器 `php.ini` 时区是 `PRC`，**新写任何 CLI 脚本都必须自己设 UTC**，否则写库时间会差 8 小时。已验证修复后 PHP 时间与 `UTC_TIMESTAMP()` 同秒。

**当前数据库只到「空结构」阶段**：7 张表（`users` `titles` `threads` `comments` `reports` `admin_audit_logs` `schema_migrations`）建好但**没有任何读写代码**，首页 4 条示例帖来自 `HomeController` 硬编码数组。不要对外声称「已支持发帖 / 有数据库内容」。

**MySQL 5.7.44 已 EOL**，且与 20 多个生产站点同实例：`kanwanliao_dev` 仅限开发阶段，不得承载真实用户数据；正式上线前须评估独立实例。不得为本项目升级或重启共享 MySQL。

### 3.3 硬约束

- 不手写 vhost 主配置（面板重建站点会覆盖手写主配置，已踩过）；要加伪静态放 `/www/server/panel/vhost/rewrite/kwl.zzzmxxkj.com.conf`（或 `extension/` 子目录）。改前先备份为 `.bak-时间戳`。
- 不删服务器文件：`.well-known/`（SSL 验证）、`.user.ini`、`404.html`、`.htaccess`、线上 `.env` 一律保留。确需清理给精确命令由用户执行。
- 不为本项目升级 / 重启共享 PHP、MySQL 或 Nginx；独立站点、独立 Web 根目录、独立数据库与最小权限账号、独立日志与备份。
- **上传代码 ≠ 可以发布未完成功能**：部署只同步代码；新增功能上线仍按阶段走用户确认与审计。
- 本文与部署脚本含服务器 IP 与站点路径，**将来推公开仓库前必须确认可见性或排除**。

## 4. 旧代码处置（已执行，2026-11-13）

用户明确授权「v1 和 v2 不需要的代码文件都可以删了」。按工作区规矩 §3.9，Agent **不得自行删除**，也不得换写法绕过拦截；已把 25 项整体**移动**到：

```
D:\workspace\_待删\BaiPiao-Community-V1V2-20261113\
```

含 V2 十个源码目录、`legacy/`（V1 归档）、`deploy/`、Nuxt 工程配置、`.nuxt/`、`.output/`、`node_modules/`、`dist`（断链符号链接）、旧 `README.md`（已改名 `README.v2.md`）。该目录里的 `README.md` 写有清单、恢复方式与用户可执行的 `rd /s /q` 删除命令。

**保留未动**：`docs/`（全部，含影视系列与历史）、`prototypes/kanwanliao/`、`.workbuddy/`、`AGENTS.md`、`TASKS.md`。

任何 Agent **不得**从待删区复制、引用旧 V1/V2 代码来继续开发；它们在产品与数据模型上均已作废。旧测试、旧静态部署、旧云端页面**一律不能当作影视论坛的完成证据**。

## 5. 文件所有权

| 路径 | 归属 | 约束 |
|---|---|---|
| `docs/影视论坛-*.md`、`docs/P00-*.md` | 用户 + 协调者 | 需求与验收真源，**未经许可不改写** |
| `docs/` 下 V1/V2/V3 与 `工具研究所-项目白皮书-V3.0.md` | 历史 | 只读追溯，不作需求依据、不删除 |
| `prototypes/kanwanliao/` | 原型 | 静态交互原型，视觉参考；其剧透折叠是 `details` 演示，**不满足服务端防泄露门禁** |
| `AGENTS.md`、`TASKS.md`、`README.md` | 协调者 | 进度只写进 `TASKS.md`；改本文要留痕并登记 |
| `.workbuddy/memory/` | 协调者 | `MEMORY.md` 覆盖式（事实变化才改）；当日流水**只追加，禁止改写** |
| `kanwanliao/` | 开发 + 协调者 | 新工程唯一目录；`public/` 是唯一 Web 根；`.runtime/`、`.env`、`storage/` 不进版本库 |
| `docs/images/` | 视觉参考 | 效果图为 AI 生成占位，片名 / 用户 / 域名 / 评分均非真实 |

**同一文件的多处编辑必须串行，一次一个**；不同文件可并行。动手前查 Git 状态与文件时间戳，不覆盖其它 Agent 未提交内容。文件一律 **UTF-8 无 BOM**，用 Write / Edit；`.sh` 用 LF；**不写 `.bat`/`.cmd`**。

## 6. 共享契约（写进代码，也写进测试）

1. **真实内容优先**：不编造用户、评论、评分、活跃度；演示数据全程醒目标注；不复制未授权的影片元数据、海报、剧照、台词。
2. **剧透防泄露**：服务端默认不输出被遮挡正文；列表、标题、摘要、搜索、分享 meta、无 JS 逐项核对，见审计方案 §3.4。
3. **权限与状态**：不存在的资源返回 404；非本人不得编辑他人内容；受限账号、已删除 / 隐藏内容不得从其它入口泄露；未审核内容可见性在实现前明确并测试。
4. **视觉基线**：最大容器约 1200px，主栏 + 约 300px 侧栏（`lg` 双栏，移动单栏）；浅色 `#F7F8FA` / `#FFFFFF` / `#1F2937` / `#6B7280` / `#E5E7EB`，强调蓝 `#2563EB`；深色主题全站覆盖且对比度可读；无巨型 Banner、玻璃拟态、夸张阴影。
5. **可访问性**：语义化 HTML、Tab 可达、可见焦点、表单错误关联、图像有替代文本；桌面与移动端实际走查，不以代码推断代替。
6. **未实现入口不得伪装可点**：禁用功能明确标注「未开放 / 筹备中」，不设无意义按钮。
7. **安全基线**（实现阶段）：密码可靠哈希 + 随机盐、会话轮换与安全 Cookie、CSRF 防护、服务端始终校验权限与输入、输出按上下文转义、速率限制、错误不泄露凭据或堆栈、管理操作留痕且日志不记录密码。
8. **数据库隔离**：只用独立测试库与专用最低权限账号；凭据不提交；迁移版本化并附回滚说明，在隔离库实测迁移 / 回滚 / 重建 / 恢复；utf8mb4，显式约定 UTC。

## 7. 硬禁令

- **不自动 `git push`**；项目尚未 `git init`，任何初始化 / 关联远端 / 提交都须用户明确指示。
- **部署已获授权**（2026-11-13 用户明确「告诉其他 agent 可以上传到宝塔」）：可执行 `bash kanwanliao/scripts/deploy.sh` 上传代码。但**上传代码不等于可以发布未完成功能**，也不得借此改动 nginx 主配置、线上 `.env`、数据库或其它站点。**新增功能上线仍须用户确认与审计。**
- **不删本地文件**（规矩 §3.9）：待删清单交用户手工执行，或移到 `D:\workspace\_待删\`；不要用 `.NET File.Delete` / `del` / 脚本绕过安全拦截。
- **不碰生产其它站点、不手写 vhost 主配置、不升级共享服务、不未经评估重启服务。**
- 改代码后按规矩 §6 往 `D:\workspace\修改记录.md` 登记（**多文件各列一条**）；不把经验自动写进 `D:\workspace\踩坑日志.md`，收尾给候选、用户同意才写。
- **对外文案铁律**：站内可见文案、README、发布说明只写读者视角（这是演示 / 筹备版本、内容为示例、暂不提供账号与发布、不提供播放或资源下载）；**不写** Nuxt / PHP / Mock / Repository / UTC / 构建 / 类名 / 文件路径等技术术语。技术细节只进本文、`TASKS.md`、`docs/`、提交信息与 `修改记录.md`。
- **用户确认前不进入下一阶段**；不擅自发布、清理、推送或扩张范围。

## 8. 每轮开工 / 收工流程

**开工**：读 `D:\workspace\规矩文档.md` → 本文 → `TASKS.md` → 核对需求文档与 `docs/` 时间戳 → 查 Git 状态与并发修改 → 确认当前阶段、文件所有权、是否已有用户开工确认。

**收工**：真跑检查并保留原始输出 → 追加 `TASKS.md`（只写真实结果，未跑写明原因）→ 登记修改记录 → 报告文件、命令、实际结果、遗留风险与回滚方案 → **停下等用户验收**。遇到共享文件冲突、环境阻塞或超出当前阶段的需求，报协调者，不自行扩张范围。

## 9. 命令速查（PHP 工程，2026-11-13 已真跑）

```bash
# 本机（Windows Git Bash）
PHP=kanwanliao/.runtime/php82/php.exe
"$PHP" kanwanliao/tests/run.php          # 零依赖测试（当前 33/33 通过）
"$PHP" -S 127.0.0.1:8099 -t kanwanliao/public kanwanliao/scripts/dev-router.php
"$PHP" kanwanliao/scripts/migrate.php status   # 需独立开发库；当前无 .env 会被安全门禁拒绝

# 部署到云端（在项目根执行，已获用户授权）
bash kanwanliao/scripts/deploy.sh

# 服务器侧（SSH ubuntu@111.231.174.142）
/www/server/php/82/bin/php -l <文件>            # 语法检查必须用 8.2，系统 php 是 7.0.33
sudo /www/server/nginx/sbin/nginx -t            # 校验 nginx 配置
sudo /www/server/nginx/sbin/nginx -s reload     # 不要用 systemctl
curl -k -s --resolve kwl.zzzmxxkj.com:443:127.0.0.1 https://kwl.zzzmxxkj.com/health
```

逐个语法检查用 `php -l` 遍历 `kanwanliao/**/*.php`（排除 `.runtime/`），当前 **21 文件 / 0 错误**。内置开发服务器必须在单次调用内「启动 → 请求 → 停服」，本机不能保持常驻进程。

不得拿旧 V2 的 `npm run test` 结果、旧静态产物或旧云端页面充当影视论坛的验收证据。真实输出一律以 `TASKS.md` 为准。
