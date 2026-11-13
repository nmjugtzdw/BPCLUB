#!/usr/bin/env bash
# 数据库备份：导出当前 .env 指向的库到 storage/backups/。
#
# 用法（在工程根执行）：
#   bash scripts/backup.sh
#   bash scripts/backup.sh --allow-prod     允许对非 _dev/_test 库执行
#
# 约束：
# - 默认拒绝备份非隔离库，避免把生产库拖进开发备份目录。
# - 备份文件含用户表与密码哈希，权限 600，目录 700，且已被 .gitignore 排除。
# - 只读：使用 --single-transaction，不锁表、不写库。
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
BACKUP_DIR="${BACKUP_DIR:-$ROOT/storage/backups}"
KEEP="${KEEP:-14}"
ALLOW_PROD=0

for arg in "$@"; do
  case "$arg" in
    --allow-prod) ALLOW_PROD=1 ;;
    *) echo "未知参数: $arg" >&2; exit 2 ;;
  esac
done

if [ ! -f "$ENV_FILE" ]; then
  echo "错误：未找到 $ENV_FILE" >&2
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

if [ "$ALLOW_PROD" -ne 1 ] && ! printf '%s' "$DB_NAME" | grep -Eq '(_test|_dev)$'; then
  echo "拒绝执行：DB_NAME=$DB_NAME 不是隔离库（以 _test / _dev 结尾）。确需执行请加 --allow-prod。" >&2
  exit 1
fi

command -v mysqldump >/dev/null 2>&1 || { echo "错误：找不到 mysqldump" >&2; exit 1; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP="$(date -u +%Y%m%d-%H%M%S)"
OUT="$BACKUP_DIR/${DB_NAME}-${STAMP}.sql.gz"
TMP="$OUT.part"

# --no-tablespaces: 低权限账号没有 PROCESS 权限时也能导出
# --single-transaction: InnoDB 一致性快照，不锁表
if ! mysqldump \
  --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password="$DB_PASS" \
  --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 \
  --set-gtid-purged=OFF \
  "$DB_NAME" 2>/dev/null | gzip -9 > "$TMP"; then
  rm -f "$TMP"
  echo "备份失败：mysqldump 返回非零。" >&2
  exit 1
fi

# 空文件或 gzip 校验失败都视为失败，绝不留下看起来正常的坏备份。
if [ ! -s "$TMP" ] || ! gzip -t "$TMP" 2>/dev/null; then
  rm -f "$TMP"
  echo "备份失败：产物为空或压缩包损坏。" >&2
  exit 1
fi

mv "$TMP" "$OUT"
chmod 600 "$OUT"

SIZE="$(wc -c < "$OUT" | tr -d ' ')"
SUM="$(sha256sum "$OUT" | cut -d' ' -f1)"
TABLES="$(gzip -dc "$OUT" | grep -c '^CREATE TABLE' || true)"

echo "备份完成"
echo "文件: $OUT"
echo "大小: $SIZE 字节"
echo "表数: $TABLES"
echo "SHA256: $SUM"

# 保留最近 $KEEP 份，其余按时间删除。
ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | while read -r old; do
  rm -f "$old"
  echo "清理旧备份: $(basename "$old")"
done
