#!/usr/bin/env bash
set -u
cd /www/wwwroot/kwl.zzzmxxkj.com || exit 1
set -a
. ./.env
set +a
Q() { mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B -e "$1" 2>/dev/null; }
echo '== 表清单 =='
Q 'SHOW TABLES;'
echo '== users 列 =='
Q 'SHOW COLUMNS FROM users;'
echo '== 行数 =='
for t in $(Q 'SHOW TABLES;'); do
  printf '%-24s %s\n' "$t" "$(Q "SELECT COUNT(*) FROM \`$t\`;")"
done
echo '== users 明细 =='
Q 'SELECT id, username, role, status, created_at FROM users ORDER BY id;'
echo '== 完 =='
