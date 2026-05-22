<?php

declare(strict_types=1);

namespace App\Service;

use GuzzleHttp\Client;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;
use function Hyperf\Support\env;

/**
 * Plisio 只做一件事: 创建加密货币发票
 */
class PlisioService
{
    private Client $client;

    public function __construct(
        ClientFactory $clientFactory,
        private LoggerInterface $logger,
    ) {
        $this->client = $clientFactory->create([
            'base_uri' => rtrim(env('PLISIO_API_URL', 'https://plisio.net/api/v1'), '/'),
            'timeout' => 30,
        ]);
    }

    /**
     * 创建发票，返回 [status, data[invoice_url, txn_id, amount, ...]]
     */
    public function createInvoice(
        string $orderNo,
        string $orderName,
        float  $amount,
        string $coin = 'USDT',
        string $callbackUrl = '',
    ): array {
        $allowedCoins = trim((string) env('ALLOWED_COINS', ''));

        $params = [
            'order_number'   => $orderNo,
            'order_name'     => $orderName,
            'source_amount'  => $amount,
            'source_currency'=> 'USD',
            'currency'       => $coin,
            'callback_url'   => $callbackUrl,
            'api_key'        => env('PLISIO_API_KEY', ''),
        ];

        if ($allowedCoins !== '') {
            $params['allowed_psys_cids'] = $allowedCoins;
        }

        try {
            $response = $this->client->get('/api/v1/invoices/new', ['query' => $params]);
            $body = $response->getBody()->getContents();
            return json_decode($body, true) ?: ['status' => 'error', 'message' => 'Invalid response'];
        } catch (\Throwable $e) {
            $this->logger->error('Plisio createInvoice error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
