<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\PaymentLog;
use function Hyperf\Support\now;

/**
 * 易支付 (Epay) 协议签名工具
 * 
 * 签名算法:
 * 1. 过滤掉 sign / sign_type 参数和空值
 * 2. 按 key 字母序排序
 * 3. 拼接为 key1=val1&key2=val2
 * 4. 末尾追加 secret key
 * 5. MD5
 */
class EpayUtil
{
    /**
     * 对参数数组签名，返回完整参数(含 sign 和 sign_type)
     */
    public static function sign(array $params, string $key): array
    {
        $params['sign_type'] = 'MD5';
        $params['sign'] = self::makeSign($params, $key);
        return $params;
    }

    /**
     * 计算签名值
     */
    public static function makeSign(array $params, string $key): string
    {
        // 1. 过滤 sign, sign_type 和空值
        $filtered = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type' || (string)$v === '') {
                continue;
            }
            $filtered[$k] = (string)$v;
        }

        // 2. 按 key 排序
        ksort($filtered);

        // 3. 拼接
        $parts = [];
        foreach ($filtered as $k => $v) {
            $parts[] = $k . '=' . $v;
        }
        $str = implode('&', $parts);

        // 4. 加 key 后 MD5
        return md5($str . $key);
    }

    /**
     * 验证签名
     */
    public static function verify(array $params, string $key): bool
    {
        $sign = $params['sign'] ?? '';
        if (empty($sign)) {
            return false;
        }
        return hash_equals($sign, self::makeSign($params, $key));
    }

    /**
     * 记录日志
     */
    public static function log(string $direction, string $orderNo, mixed $request, mixed $response): void
    {
        try {
            PaymentLog::create([
                'direction' => $direction,
                'order_no' => $orderNo,
                'request' => is_string($request) ? $request : json_encode($request, JSON_UNESCAPED_UNICODE),
                'response' => is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // 日志记录失败不影响主流程
        }
    }
}
