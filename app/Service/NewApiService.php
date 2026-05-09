<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\PaymentLog;
use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use Psr\Log\LoggerInterface;
use function Hyperf\Support\env;

/**
 * NewAPI 回调转发 — Plisio IPN 到达后，构建 epay 回调参数 POST 到 notify_url
 */
class NewApiService
{
    private Client $client;

    public function __construct(
        ClientFactory $clientFactory,
        private LoggerInterface $logger,
    ) {
        $this->client = $clientFactory->create(['timeout' => 30]);
    }

    /**
     * 构建 epay 回调参数并 POST 到 notify_url
     *
     * @param array $plisioData Plisio IPN 原始数据
     * @param array $epayParams 创建发票时保存的 epay 请求参数 (pid, type, out_trade_no, notify_url, name, money)
     */
    public function sendEpayCallback(array $plisioData, array $epayParams): array
    {
        $notifyUrl = $epayParams['notify_url'] ?? '';
        $key = env('EPAY_KEY', '');

        $callbackParams = [
            'pid'          => $epayParams['pid'] ?? '',
            'trade_no'     => $plisioData['txn_id'] ?? '',
            'out_trade_no' => $epayParams['out_trade_no'] ?? '',
            'type'         => $epayParams['type'] ?? '',
            'name'         => $epayParams['name'] ?? '',
            'money'        => $epayParams['money'] ?? '',
            'trade_status' => 'TRADE_SUCCESS',
        ];

        // 签名
        $callbackParams = EpayUtil::sign($callbackParams, $key);

        $orderNo = $epayParams['out_trade_no'] ?? '';

        try {
            $response = $this->client->post($notifyUrl, [
                'form_params' => $callbackParams,
            ]);
            $body = $response->getBody()->getContents();

            EpayUtil::log(PaymentLog::DIRECTION_CALLBACK, $orderNo, $callbackParams, $body);

            return ['success' => true, 'body' => $body];
        } catch (\Throwable $e) {
            $this->logger->error('NewAPI callback error: ' . $e->getMessage());
            EpayUtil::log(PaymentLog::DIRECTION_CALLBACK, $orderNo, $callbackParams, ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
