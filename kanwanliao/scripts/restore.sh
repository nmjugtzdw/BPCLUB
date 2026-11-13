#!/usr/bin/env bash
# 数据库恢复：把备份导入一个隔离目标库。
#
# 用法：
#   bash scripts/restore.sh <备份文件.sql.gz> --target=<库名> --yes
#
# 安全约束：
# - 目标库名必须以 _test / _dev 结尾，且不能等于当前 .env 的 DB_NAME。
# - 必须显式 --yes；恢复会覆盖目标库里的同名表。
# - 目标库必须已存在：本项目使用的低权限账号无权建库，建库需由有权限的
#   管理员单独完成，脚本不会尝试创建或提升权限。
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"

DUMP=""
TARGET=""
CONFIRMED=0

for arg in "$@"; do
  case "$arg" in
    --target=*) TARGET="${arg#--target=}" ;;
    --yes) CONFIRMED=1 ;;
    -*) echo "未知参数: $arg" >&2; exit 2 ;;
    *) DUMP="$arg" ;;
  esac
done

if [ -z "$DUMP" ] || [ -z "$TARGET" ]; then
  echo "用法: bash scripts/restore.sh <备份文件.sql.gz> --target=<库名> --yes" >&2
  exit 2
fi
if [ "$CONFIRMED" -ne 1 ]; then
  echo "拒绝执行：恢复会覆盖目标库内容，请确认后加 --yes。" >&2
  exit 1
fi
if [ ! -f "$DUMP" ]; then
  echo "错误：备份文件不存在: $DUMP" >&2
  exit 1
fi
if ! printf '%s' "$TARGET" | grep -Eq '(_test|_dev)$'; then
  echo "拒绝执行：目标库 $TARGET 不是隔离库（以 _test / _dev 结尾）。" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
. "$ENV_FILE"
set +a

: "${DB_HOST:?缺少 DB_HOST}"
: "${DB_PORT:?缺少 DB_PORT}"
: "${DB_NAME:?缺少 DB_NAME}"
: "${DB_USER:?缺少 DB_USER}"
: "${DB_PASS:?缺少 DB_PASS}"

if [ "$TARGET" = "$DB_NAME" ]; then
  echo "拒绝执行：目标库与当前运行库相同（$DB_NAME），恢复会破坏在跑的数据。" >&2
  exit 1
fi

if ! gzip -t "$DUMP" 2>/dev/null; then
  echo "错误：备份文件损坏或不是 gzip。" >&2
  exit 1
fi

MYSQL=(mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" --default-character-set=utf8mb4)

if ! "${MYSQL[@]}" -e "SELECT 1" "$TARGET" >/dev/null 2>&1; then
  echo "错误：无法连接目标库 $TARGET。请确认它已存在，且当前账号有权限。" >&2
  exit 1
fi

echo "恢复 $DUMP -> $TARGET"
gzip -dc "$DUMP" | "${MYSQL[@]}" "$TARGET"

TABLES="$("${MYSQL[@]}" -N -e 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()' "$TARGET")"
echo "恢复完成：$TARGET 现有 $TABLES 张表"
