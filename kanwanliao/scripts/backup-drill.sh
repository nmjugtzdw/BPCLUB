#!/usr/bin/env bash
# 备份恢复演练（隔离校验）。
#
# 用途：验证「备份文件真的能恢复出完整数据」，同时不碰正在使用的表。
#
# 做法：
#   1. 用 mysqldump 导出数据（不含建表语句）
#   2. 在同一个库里用 CREATE TABLE ... LIKE 克隆一份带前缀的临时表
#      （LIKE 不复制外键，因此导入不会被约束卡住，也不会影响原表）
#   3. 把导出里的表名替换成克隆名后导入
#   4. 逐表比对行数，全部一致才算通过
#   5. 无论成败都删除克隆表
#
# 为什么不用「新建一个库再恢复」：本项目的低权限账号无权建库（实测 1044），
# 而共享 MySQL 实例的管理员凭据不由本项目保存。真正的新库恢复需要管理员
# 单独建库并授权，步骤见运维手册。
#
# 用法：bash scripts/backup-drill.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
set -a
# shellcheck disable=SC1090
. "$ROOT/.env"
set +a

: "${DB_HOST:?}" "${DB_PORT:?}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASS:?}"

if ! printf '%s' "$DB_NAME" | grep -Eq '(_test|_dev)$'; then
  echo "拒绝执行：DB_NAME=$DB_NAME 不是隔离库。" >&2
  exit 1
fi

MYSQL=(mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" \
  --default-character-set=utf8mb4 -N "$DB_NAME")

STAMP="$(date -u +%Y%m%d%H%M%S)"
PREFIX="bkdrill_${STAMP}_"
TMPDIR_LOCAL="$(mktemp -d)"
DUMP="$TMPDIR_LOCAL/data.sql"
RENAMED="$TMPDIR_LOCAL/data-renamed.sql"

TABLES="$("${MYSQL[@]}" -e "SELECT table_name FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE 'bkdrill\\_%' ORDER BY table_name")"

if [ -z "$TABLES" ]; then
  echo "错误：没有可演练的表。" >&2
  exit 1
fi

cleanup() {
  for t in $TABLES; do
    "${MYSQL[@]}" -e "DROP TABLE IF EXISTS \`${PREFIX}${t}\`" >/dev/null 2>&1 || true
  done
  rm -rf "$TMPDIR_LOCAL"
}
trap cleanup EXIT

echo "演练库: $DB_NAME"
echo "临时表前缀: $PREFIX"
echo "表: $(echo "$TABLES" | tr '\n' ' ')"

# 1. 导出数据
mysqldump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" \
  --single-transaction --quick --no-tablespaces --no-create-info --skip-triggers \
  --default-character-set=utf8mb4 --set-gtid-purged=OFF "$DB_NAME" > "$DUMP" 2>/dev/null
echo "导出大小: $(wc -c < "$DUMP" | tr -d ' ') 字节"

# 2. 克隆表结构
for t in $TABLES; do
  "${MYSQL[@]}" -e "CREATE TABLE \`${PREFIX}${t}\` LIKE \`${t}\`"
done

# 3. 表名替换
SED_ARGS=()
for t in $TABLES; do
  SED_ARGS+=(-e "s/\`${t}\`/\`${PREFIX}${t}\`/g")
done
sed "${SED_ARGS[@]}" "$DUMP" > "$RENAMED"

# 4. 导入克隆表
"${MYSQL[@]}" < "$RENAMED"

# 5. 比对行数
FAILED=0
printf '%-24s %10s %10s %s\n' "表" "原表" "恢复" "结果"
for t in $TABLES; do
  ORIG="$("${MYSQL[@]}" -e "SELECT COUNT(*) FROM \`${t}\`")"
  COPY="$("${MYSQL[@]}" -e "SELECT COUNT(*) FROM \`${PREFIX}${t}\`")"
  if [ "$ORIG" = "$COPY" ]; then
    printf '%-24s %10s %10s %s\n' "$t" "$ORIG" "$COPY" "一致"
  else
    printf '%-24s %10s %10s %s\n' "$t" "$ORIG" "$COPY" "不一致"
    FAILED=1
  fi
done

if [ "$FAILED" -ne 0 ]; then
  echo "演练结果: 不通过（存在行数不一致的表）"
  exit 1
fi

echo "演练结果: 通过（全部表行数一致，克隆表已清理）"
