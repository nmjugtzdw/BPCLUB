#!/usr/bin/env bash
# 把 kanwanliao 同步到云端站点（仅同步代码，不碰线上配置与数据）。
#
# 用法（在项目根 D:\workspace\BaiPiao-Community 下执行）：
#   bash kanwanliao/scripts/deploy.sh
#
# 设计约束：
# - 只上传代码与静态资源，绝不覆盖服务器上的 .env、storage/ 运行数据。
# - 不删除服务器文件（tar 为覆盖式）；.user.ini / .well-known / 404.html 一律保留。
# - .runtime/ 是本机 PHP 便携版，119MB，永不上传。
# - 不改动 nginx 配置；运行目录与伪静态已由宝塔面板设置，见 README「部署」一节。
set -euo pipefail

REMOTE_HOST="${REMOTE_HOST:-ubuntu@111.231.174.142}"
REMOTE_DIR="${REMOTE_DIR:-/www/wwwroot/kwl.zzzmxxkj.com}"
LOCAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ ! -f "$LOCAL_DIR/public/index.php" ]; then
  echo "错误：未找到 $LOCAL_DIR/public/index.php，请在正确的工程目录执行。" >&2
  exit 1
fi

echo "同步 $LOCAL_DIR -> $REMOTE_HOST:$REMOTE_DIR"

tar czf - -C "$LOCAL_DIR" \
  --exclude='./.runtime' \
  --exclude='./.env' \
  --exclude='./storage/logs/*.log' \
  --exclude='./storage/cache/*' \
  --exclude='./storage/sessions/*' \
  app config resources public scripts database tests docs README.md .gitignore .env.example \
| ssh -o BatchMode=yes -o ConnectTimeout=10 "$REMOTE_HOST" \
  "sudo tar xzf - -C '$REMOTE_DIR' \
   && sudo find '$REMOTE_DIR' -path '$REMOTE_DIR/.well-known' -prune -o ! -name '.user.ini' ! -name '.env' -exec chown www:www {} +"

echo "上传完成。"
echo "线上自检："
ssh -o BatchMode=yes -o ConnectTimeout=10 "$REMOTE_HOST" \
  "curl -k -s --resolve kwl.zzzmxxkj.com:443:127.0.0.1 https://kwl.zzzmxxkj.com/health; echo"
