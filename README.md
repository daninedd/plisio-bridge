# Plisio Bridge

NewAPI 易支付网关 → Plisio 加密货币支付的桥梁。

## 两个端点

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /submit.php | 易支付 Purchase (NewAPI go-epay 默认请求此路径) |
| POST | /api/callback/plisio | Plisio IPN 回调 |

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

## 部署

与 new-api 共用 MySQL/Redis，加入 `new-api-network`。..

```bash
# 1. 先确保 new-api 已启动
cd ~/GolandProjects/new-api && docker compose up -d

# 2. 在 new-api 的 MySQL 中创建 plisio_bridge 库
docker compose exec mysql mysql -uroot -pWelcome1++ \
  -e "CREATE DATABASE IF NOT EXISTS plisio_bridge DEFAULT CHARSET utf8mb4;"

# 3. 配置环境变量
cd ~/PhpstormProjects/plisio-bridge
cp .env.production .env
vim .env  # 填入 EPAY_PID, EPAY_KEY, PLISIO_API_KEY, APP_URL

# 4. 启动
docker compose up -d

# 5. 建表
docker compose exec app php bin/hyperf.php migrate
```

## NewAPI 后台配置

```
支付地址 (PayAddress): https://your-domain.com
商户ID (EpayId):      与 EPAY_PID 一致
商户密钥 (EpayKey):    与 EPAY_KEY 一致
```
