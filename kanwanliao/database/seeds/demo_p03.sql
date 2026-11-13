-- P03 演示数据 · 全部为虚构示例，不是真实影视资料、也不是真实用户言论
--
-- 只允许在隔离开发库（库名以 _dev / _test 结尾）手动执行。
-- 演示账号状态为 disabled 且密码哈希是不可用的占位串：它们只用于给示例帖子
-- 署名，不能登录，也不代表真实注册用户。
-- 覆盖的边界：四种分类、剧透帖与非剧透帖、已隐藏帖、受限账号的帖子、
-- 同名作品（不同年份 / 不同类型）、未关联作品的帖子、无正文帖、剧透评论。

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- 演示作品。同名不同年份 / 类型的两部用来验证「同名作品不合并」。
INSERT INTO titles (name, work_type, release_year, synopsis, source_label, last_verified_at, created_at) VALUES
  ('风起小城（示例作品）', 'series', 2026, '演示用虚构剧集，不是真实影视资料。', '演示虚构', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('雨后的车站（示例作品）', 'movie', 2025, '演示用虚构影片，不是真实影视资料。', '演示虚构', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('长夜灯火（示例作品）', 'series', 2024, '演示用虚构剧集，2024 年版。', '演示虚构', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('长夜灯火（示例作品）', 'movie', 1998, '演示用虚构影片，与同名剧集不是同一部作品。', '演示虚构', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('未完成的夏天（示例作品）', 'movie', 2023, '演示用虚构影片，暂无讨论。', '演示虚构', NULL, '2026-11-13 00:00:00');

-- 演示账号。password_hash 是不可用的占位串，无法通过任何密码校验，
-- 因此这些账号只能给示例帖子署名，不能登录；列表中要求作者状态 active，
-- 所以这里用 active，但它与「有可登录账号」是两件事。
INSERT INTO users (username, email, password_hash, role, status, created_at, updated_at) VALUES
  ('示例观众甲', 'demo-a@example.invalid', '!demo-account-cannot-login', 'user', 'active', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('示例观众乙', 'demo-b@example.invalid', '!demo-account-cannot-login', 'user', 'active', '2026-11-13 00:00:00', '2026-11-13 00:00:00'),
  ('示例观众丙', 'demo-c@example.invalid', '!demo-account-cannot-login', 'user', 'active', '2026-11-13 00:00:00', '2026-11-13 00:00:00');

INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'series', '《风起小城》完结了，结尾那段你怎么看？',
       '演示正文：结尾把埋了很久的那条线索收回来了，但节奏明显赶。这是我自己的观感，不是剧情简介。',
       (SELECT id FROM titles WHERE name = '风起小城（示例作品）' AND work_type = 'series' LIMIT 1),
       1, 'visible', '2026-11-12 20:10:00', '2026-11-12 20:10:00'
FROM users u WHERE u.email = 'demo-a@example.invalid';

INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'movie', '《雨后的车站》：适合周末慢慢看的一部',
       '演示正文：镜头很克制，留白比台词说得多。看完之后在座位上坐了一会儿才走。',
       (SELECT id FROM titles WHERE name = '雨后的车站（示例作品）' AND work_type = 'movie' LIMIT 1),
       0, 'visible', '2026-11-12 18:40:00', '2026-11-12 18:40:00'
FROM users u WHERE u.email = 'demo-b@example.invalid';

INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'recommend', '最近剧荒，想找一部节奏轻快的悬疑剧',
       '演示正文：希望集数别太长，周末能刷完的那种。有没有类似的推荐？',
       NULL, 0, 'visible', '2026-11-12 15:05:00', '2026-11-12 15:05:00'
FROM users u WHERE u.email = 'demo-c@example.invalid';

INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'chat', '有没有一部你看过很多次的电影？',
       '演示正文：有些片子每隔几年翻出来看，感受都不一样。我的那部一直没变。',
       NULL, 0, 'visible', '2026-11-11 21:30:00', '2026-11-11 21:30:00'
FROM users u WHERE u.email = 'demo-a@example.invalid';

-- 同名作品的另一部：1998 年电影版，验证作品页各自独立。
INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'movie', '1998 年那版《长夜灯火》，现在看还是很有味道',
       '演示正文：老片子的节奏慢，但这种慢是有意的。和后来的剧集版完全是两件事。',
       (SELECT id FROM titles WHERE name = '长夜灯火（示例作品）' AND work_type = 'movie' LIMIT 1),
       0, 'visible', '2026-11-11 19:15:00', '2026-11-11 19:15:00'
FROM users u WHERE u.email = 'demo-b@example.invalid';

-- 无正文帖：验证「这条讨论没有正文」的边界。
INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'chat', '只是想说一句：今晚的影院人真多',
       '', NULL, 0, 'visible', '2026-11-10 22:00:00', '2026-11-10 22:00:00'
FROM users u WHERE u.email = 'demo-c@example.invalid';

-- 剧透帖，正文含一段用于泄露检测的独特字符串。
INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'series', '《长夜灯火》剧集版第五集那个转折，有人看出来了吗',
       '演示正文：其实第五集一开始就给了暗示，那个反复出现的旧钥匙就是答案，后面所有解释都建立在这上面。',
       (SELECT id FROM titles WHERE name = '长夜灯火（示例作品）' AND work_type = 'series' LIMIT 1),
       1, 'visible', '2026-11-10 20:30:00', '2026-11-10 20:30:00'
FROM users u WHERE u.email = 'demo-a@example.invalid';

-- 已隐藏帖：不得出现在任何公开入口。
INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'chat', '这条帖子已被隐藏，不应该出现在任何列表里',
       '演示正文：隐藏内容泄露检测用。',
       NULL, 0, 'hidden', '2026-11-10 10:00:00', '2026-11-10 10:00:00'
FROM users u WHERE u.email = 'demo-b@example.invalid';

-- 待审核帖：同样不得出现在公开列表。
INSERT INTO threads (author_id, category, title, body, title_id, is_spoiler, status, created_at, updated_at)
SELECT u.id, 'movie', '这条帖子还在审核中，不应该出现在任何列表里',
       '演示正文：待审核内容泄露检测用。',
       NULL, 0, 'pending', '2026-11-10 09:00:00', '2026-11-10 09:00:00'
FROM users u WHERE u.email = 'demo-c@example.invalid';

-- 评论：一条普通、一条剧透（含检测用独特字符串）。
INSERT INTO comments (thread_id, author_id, body, is_spoiler, status, created_at)
SELECT t.id, u.id, '演示评论：同感，那段我也回看了一遍。', 0, 'visible', '2026-11-12 21:00:00'
FROM threads t, users u
WHERE t.title = '《风起小城》完结了，结尾那段你怎么看？' AND u.email = 'demo-b@example.invalid';

INSERT INTO comments (thread_id, author_id, body, is_spoiler, status, created_at)
SELECT t.id, u.id, '演示评论（剧透）：其实那个配角早在第三集就露过面，只是当时没给正脸。', 1, 'visible', '2026-11-12 21:10:00'
FROM threads t, users u
WHERE t.title = '《风起小城》完结了，结尾那段你怎么看？' AND u.email = 'demo-c@example.invalid';

INSERT INTO comments (thread_id, author_id, body, is_spoiler, status, created_at)
SELECT t.id, u.id, '演示评论：周末就去补，谢谢推荐。', 0, 'visible', '2026-11-12 19:20:00'
FROM threads t, users u
WHERE t.title = '《雨后的车站》：适合周末慢慢看的一部' AND u.email = 'demo-c@example.invalid';

-- 已隐藏评论：不得出现在详情页。
INSERT INTO comments (thread_id, author_id, body, is_spoiler, status, created_at)
SELECT t.id, u.id, '演示评论：这条评论已被隐藏，不应该出现在详情页。', 0, 'hidden', '2026-11-12 22:00:00'
FROM threads t, users u
WHERE t.title = '《雨后的车站》：适合周末慢慢看的一部' AND u.email = 'demo-a@example.invalid';
