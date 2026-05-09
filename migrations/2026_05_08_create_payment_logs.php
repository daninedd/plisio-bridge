<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreatePaymentLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('direction', 16)->comment('方向: request/callback');
            $table->string('order_no', 64)->default('')->comment('订单号');
            $table->mediumText('request')->nullable()->comment('请求数据');
            $table->mediumText('response')->nullable()->comment('响应数据');
            $table->dateTime('created_at')->nullable()->comment('创建时间');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
}
