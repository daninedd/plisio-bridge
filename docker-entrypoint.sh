#!/bin/sh
set -e

# 首次启动或 composer.json 变化时自动安装依赖
if [ ! -d vendor ] || [ composer.json -nt vendor/autoload.php ]; then
    echo "Installing dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

mkdir -p runtime logs
chmod -R 777 runtime logs

exec php bin/hyperf.php start
