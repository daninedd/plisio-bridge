# Plisio Bridge

NewAPI 易支付网关 → Plisio 加密货币支付的桥梁。

## 流程

```
NewAPI (go-epay) ──GET /submit.php?pid=...&sign=...──→ Plisio Bridge
                                                            │
                                             验证 MD5 签名 → 创建 Plisio 发票
                                                            │
                                             302 重定向 ───→ Plisio 支付页
                                                            │
                                         用户支付加密货币      │
                                                            │
                    Plisio IPN ←── POST /api/callback/plisio ─┘
                         │
              构建 epay 回调参数 (TRADE_SUCCESS + 签名)
                         │
         POST notify_url → NewAPI 确认支付
```

## 端点

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /submit.php | 易支付 Purchase (go-epay 默认路径) |
| POST | /api/callback/plisio | Plisio IPN 回调 |

## 架构

与 new-api 共用 MySQL 和 Redis，通过 Docker 网络 `new-api_new-api-network` 互联。

```
plisio-bridge (9501) ──→ mysql:3306 (库: plisio_bridge)
                      ──→ redis:6379
```

## 部署

### 首次部署

```bash
# 1. 确保 new-api 已启动 (创建网络和数据库容器)
cd ~/GolandProjects/new-api
docker compose up -d

# 2. 在 MySQL 中创建 plisio_bridge 库
docker compose exec mysql mysql -uroot -pWelcome1++ \
  -e "CREATE DATABASE IF NOT EXISTS plisio_bridge DEFAULT CHARSET utf8mb4;"

# 3.配置
```

`.env` 必填项:

```
EPAY_PID=            # 商户ID (需与 NewAPI 后台一致)
EPAY_KEY=            # 商户密钥 (需与 NewAPI 后台一致)
PLISIO_API_KEY=      # Plisio API Key
APP_URL=             # 本服务公网地址 (如 https://pay.example.com)
```

```bash
# 4. 构建并启动
docker compose up -d --build

# 5. 数据库迁移 (仅首次)
docker compose exec app php bin/hyperf.php migrate

# 6. 验证
curl http://localhost:9501/submit.php
# 应返回 "签名验证失败" (正常 — 未带签名参数)
```

### NewAPI 后台配置

| 配置项 | 值 |
|--------|-----|
| 支付地址 (PayAddress) | `https://你的域名` |
| 商户ID (EpayId) | 与 `EPAY_PID` 一致 |
| 商户密钥 (EpayKey) | 与 `EPAY_KEY` 一致 |

## 日常开发

代码通过 volume 挂载进容器，大部分修改无需重建。

```
docker compose ps               # 查看状态
docker compose logs -f app      # 查看日志
docker compose restart          # 重启 (改 app/ 代码后)
docker compose up -d --build    # 重建 (改 config/migrations/composer.json 后)
docker compose exec app php bin/hyperf.php migrate  # 运行迁移
```

**只改了 `app/` 下的代码**: `docker compose restart` 即可生效，无需重建。

**改了 config/、migrations/、composer.json、Dockerfile**: 需要 `docker compose up -d --build`。

## 常见问题

### network declared as external, but could not be found

Docker Compose 会给网络加项目名前缀。new-api 目录名是 `new-api`，实际网络名是 `new-api_new-api-network`。

确认: `docker network ls | grep new-api`

### Nothing to migrate

表已存在则跳过。确认:

```bash
docker compose -f ~/GolandProjects/new-api/docker-compose.yml exec mysql \
  mysql -uroot -pWelcome1++ plisio_bridge -e "SHOW TABLES;"
```

看到 `payment_logs` 即迁移成功。

### Cannot declare class ... already in use

Swoole 环境下迁移文件被重复加载。先查表是否存在:

```bash
docker compose exec app php bin/hyperf.php migrate:status
```

如表已存在 (`payment_logs`)，忽略此错误即可。

### 容器启动失败 vendor/autoload.php not found

`.dockerignore` 白名单里缺少对应目录。确保包含:

```
!app/
!bin/
!config/
!migrations/
!composer.*
```
