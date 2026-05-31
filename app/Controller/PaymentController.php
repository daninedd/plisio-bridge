<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\PaymentLog;
use App\Service\EpayUtil;
use App\Service\NewApiService;
use App\Service\PlisioService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Log\LoggerInterface;
use function Hyperf\Support\env;

class PaymentController extends AbstractController
{
    public function __construct(
        private PlisioService $plisio,
        private NewApiService $newApi,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * GET /submit.php
     * 
     * 易支付 Purchase 端点。
     * NewAPI 的 go-epay 库会 GET 请求此地址，携带:
     *   pid, type, out_trade_no, notify_url, return_url, name, money, device, sign, sign_type
     * 
     * 流程: 验证签名 → 创建 Plisio 发票 → 302 重定向到 Plisio 支付页
     */
    public function submit(RequestInterface $request, ResponseInterface $response)
    {
        $params = $request->all();
        $key = env('EPAY_KEY', '');

        // 验证签名
        if (!EpayUtil::verify($params, $key)) {
            $this->logger->warning('Epay sign verify failed', ['params' => $params]);
            return $response->raw('签名验证失败')->withStatus(403);
        }

        // 验证 pid
        $expectedPid = env('EPAY_PID', '');
        if ($expectedPid !== '' && ($params['pid'] ?? '') !== $expectedPid) {
            $this->logger->warning('Epay pid mismatch', ['got' => $params['pid'] ?? '', 'expected' => $expectedPid]);
            return $response->raw('商户ID错误')->withStatus(403);
        }

        $orderNo   = $params['out_trade_no'] ?? '';
        $money     = (float)($params['money'] ?? 0);
        $coin      = env('DEFAULT_COIN', 'USDT');
        $orderName = $params['name'] ?? "Order {$orderNo}";
        $callbackUrl = env('APP_URL', '') . '/api/callback/plisio';
        $returnUrl = $params['return_url'] ?? '';

        // 调用 Plisio 创建发票
        $result = $this->plisio->createInvoice($orderNo, $orderName, $money, $coin, $callbackUrl, $returnUrl);

        // 记录 epay 请求参数 (回调时需要从中取 notify_url 等)
        EpayUtil::log(PaymentLog::DIRECTION_REQUEST, $orderNo, $params, $result);

        if (($result['status'] ?? '') !== 'success') {
            $this->logger->error('Plisio invoice failed', ['order_no' => $orderNo, 'result' => $result]);
            return $response->raw('创建支付订单失败');
        }

        $invoiceUrl = $result['data']['invoice_url'] ?? '';

        if (empty($invoiceUrl)) {
            return $response->raw('未获取到支付链接');
        }

        // 302 重定向到 Plisio 支付页面
        return $response->redirect($invoiceUrl, 302);
    }

    /**
     * POST /api/callback/plisio
     * 
     * Plisio IPN 回调 → 构建 epay 回调参数 → POST 到 NewAPI 的 notify_url
     */
    public function callback(RequestInterface $request, ResponseInterface $response)
    {
        $rawBody = $request->getBody()->getContents();
        $data = json_decode($rawBody, true) ?: $request->all();

        $orderNo = $data['order_number'] ?? '';

        $this->logger->info('Plisio callback received', ['order_no' => $orderNo, 'status' => $data['status'] ?? '']);

        // 只处理 completed 状态
        if (($data['status'] ?? '') !== 'completed') {
            return $response->raw('ignored');
        }

        // 从日志中取出创建发票时保存的 epay 参数
        $epayLog = PaymentLog::where('direction', PaymentLog::DIRECTION_REQUEST)
            ->where('order_no', $orderNo)
            ->first();

        $epayParams = [];
        if ($epayLog && $epayLog->request) {
            $epayParams = json_decode($epayLog->request, true) ?: [];
        }

        if (empty($epayParams) || empty($epayParams['notify_url'] ?? '')) {
            $this->logger->warning('No epay params found for callback', ['order_no' => $orderNo]);
            // 没有 notify_url —> 仅记录，无法回调
            EpayUtil::log(PaymentLog::DIRECTION_CALLBACK, $orderNo, $data, ['skipped' => 'no notify_url']);
            return $response->raw('logged');
        }

        // 构建并发送 epay 回调
        $this->newApi->sendEpayCallback($data, $epayParams);

        // 返回 success 给 Plisio (否则 Plisio 会重试)
        return $response->raw('success');
    }
}
