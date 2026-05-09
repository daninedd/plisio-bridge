<?php

declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;

// 易支付 Purchase 端点 (NewAPI go-epay 库默认请求此路径)
Router::post('/submit.php', 'App\Controller\PaymentController@submit');

// Plisio IPN 回调
Router::post('/api/callback/plisio', 'App\Controller\PaymentController@callback');
