#!/usr/bin/env bash
set -u
cd /www/wwwroot/kwl.zzzmxxkj.com || exit 1
set -a; . ./.env; set +a
Q() { mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -B -e "$1" 2>/dev/null; }
echo '== users =='
Q 'SELECT id, username, role, status, created_at FROM users ORDER BY id;'
echo '== threads 状态 =='
Q 'SELECT id, author_id, status, is_spoiler, LEFT(title,28) AS title, created_at FROM threads ORDER BY id;'
echo '== comments 状态 =='
Q 'SELECT id, thread_id, author_id, status, LEFT(body,24) AS body, created_at FROM comments ORDER BY id;'
echo '== 审计日志 =='
Q 'SELECT id, actor_id, action, LEFT(target_type,12) AS ttype, target_id, created_at FROM admin_audit_logs ORDER BY id DESC LIMIT 10;'
