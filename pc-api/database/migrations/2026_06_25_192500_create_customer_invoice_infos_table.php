<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // v0.5.8.9 客户开票信息 — 1 客户可有多条 (增值税普通发票 / 增值税专用发票 / 通用)
        Schema::create('customer_invoice_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            // 开票类型: general=普通发票, special=增值税专用发票, electronic=电子发票
            $table->string('invoice_type', 20)->default('general');
            // 单位名称 (开票抬头)
            $table->string('company_name', 200);
            // 纳税人识别号 (税号)
            $table->string('tax_no', 50);
            // 注册地址
            $table->string('register_address', 200)->nullable();
            // 注册电话
            $table->string('register_phone', 32)->nullable();
            // 开户银行
            $table->string('bank_name', 100)->nullable();
            // 银行账号
            $table->string('bank_account', 50)->nullable();
            // 是否默认开票信息
            $table->boolean('is_default')->default(false);
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index('customer_id');
        });

        // GRANT (用 OA 自己的数据库用户)
        \Illuminate\Support\Facades\DB::statement('GRANT ALL PRIVILEGES ON TABLE customer_invoice_infos TO oa_user');
        \Illuminate\Support\Facades\DB::statement('GRANT USAGE, SELECT ON SEQUENCE customer_invoice_infos_id_seq TO oa_user');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_infos');
    }
};
