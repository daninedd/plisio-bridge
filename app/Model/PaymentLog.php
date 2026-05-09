<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;

/**
 * 支付日志 — 仅记录，不做业务查询
 * 
 * @property int $id
 * @property string $direction  方向: 'request'(NewAPI->Plisio) 或 'callback'(Plisio->NewAPI)
 * @property string $order_no   订单号
 * @property string $request    请求原始数据 (JSON)
 * @property string $response   响应原始数据 (JSON)
 * @property Carbon $created_at
 */
class PaymentLog extends Model
{
    public const DIRECTION_REQUEST = 'request';
    public const DIRECTION_CALLBACK = 'callback';

    protected ?string $table = 'payment_logs';

    protected array $fillable = [
        'direction',
        'order_no',
        'request',
        'response',
    ];

    protected array $casts = [
        'id' => 'integer',
        'created_at' => 'datetime',
    ];

    public bool $timestamps = false;
}
