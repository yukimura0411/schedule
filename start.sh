#!/bin/bash

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

PORT=8000
# 利用可能なポートを探索
while lsof -i :$PORT >/dev/null 2>&1; do
    PORT=$((PORT + 1))
done

echo "=================================================="
echo "  📅 カレンダー Webアプリケーション"
echo "=================================================="
echo "  ローカルサーバーを起動しています..."
echo "  URL: http://localhost:$PORT"
echo "  終了するには [Ctrl + C] を押してください。"
echo "=================================================="

# バックグラウンドでブラウザを開く
(sleep 1 && open "http://localhost:$PORT") &

# PHPビルトインWebサーバーを起動
php -S localhost:$PORT
