#!/bin/sh
# 双版本回归：PHP 7.4 (vendor7) 与 PHP 8.5 (vendor85) 各跑全量 phpunit
# 依赖 docker.genee.cn/php:{7.4,8.5}-alpine 镜像（amd64；arm64 主机可跑但有 warning）
set -e

# refuse to clobber a real vendor directory: this script only manages a symlink
[ -e vendor ] && [ ! -L vendor ] && { echo "refuse: real vendor dir exists" >&2; exit 1; }

cleanup() {
    [ -L vendor ] && rm -f vendor   # 仅清映射产生的符号链接，绝不动真实 vendor 目录
    rm -f cache/class_map.json      # 清掉容器内绝对路径的类映射缓存
}
trap cleanup EXIT

rm -f cache/class_map.json

for pair in "7.4 vendor7" "8.5 vendor85"; do
    set -- $pair
    v=$1
    vd=$2
    echo "=== PHP $v ($vd) ==="
    docker run --rm -v "$PWD":/app -w /app "docker.genee.cn/php:$v-alpine" \
        sh -c "ln -sfn $vd vendor && $vd/bin/phpunit"
done
