<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * V0.4.2 供应商档案
 *
 * V0.3.18 已建表（简单结构：name/contact_person/phone/email/address/category/rating/status/notes）
 * V0.4.2 扩展：code/type/business_license/legal_person/registered_capital/website/
 *          bank_name/bank_account/account_name/tax_no/payment_terms/created_by/remark
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            // 全新部署：完整建表
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique()->comment('供应商编号');
                $table->string('name', 200)->comment('供应商名称');
                $table->enum('type', ['material', 'labor', 'outsource', 'service'])
                    ->default('material')->comment('类型');
                $table->string('contact_person', 50)->nullable()->comment('主联系人');
                $table->string('phone', 20)->nullable()->comment('联系电话');
                $table->string('email', 100)->nullable()->comment('邮箱');
                $table->string('address', 255)->nullable()->comment('地址');
                $table->string('category', 100)->nullable()->comment('类别');
                $table->string('business_license', 50)->nullable()->comment('营业执照号');
                $table->string('legal_person', 50)->nullable()->comment('法人');
                $table->decimal('registered_capital', 14, 2)->nullable()->comment('注册资本(元)');
                $table->string('website', 200)->nullable()->comment('官网');
                $table->string('bank_name', 100)->nullable()->comment('开户行');
                $table->string('bank_account', 50)->nullable()->comment('银行账号');
                $table->string('account_name', 100)->nullable()->comment('账户名');
                $table->string('tax_no', 50)->nullable()->comment('税号');
                $table->enum('payment_terms', ['cash', '30days', '60days', '90days'])
                    ->default('30days')->comment('账期');
                $table->unsignedTinyInteger('rating')->default(3)->comment('评级(1-5)');
                $table->enum('status', ['active', 'paused', 'blacklist'])
                    ->default('active')->comment('状态');
                $table->text('remark')->nullable()->comment('备注');
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->timestamps();

                $table->index('type');
                $table->index('status');
                $table->index('rating');
            });
            return;
        }

        // 已存在：补充 V0.4.2 新增字段（幂等 ALTER）
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'code')) {
                $table->string('code', 30)->nullable()->after('id')->comment('供应商编号');
                $table->unique('code');
            }
            if (!Schema::hasColumn('suppliers', 'type')) {
                $table->enum('type', ['material', 'labor', 'outsource', 'service'])
                    ->default('material')->after('code')->comment('类型');
            }
            if (!Schema::hasColumn('suppliers', 'business_license')) {
                $table->string('business_license', 50)->nullable()->comment('营业执照号');
            }
            if (!Schema::hasColumn('suppliers', 'legal_person')) {
                $table->string('legal_person', 50)->nullable()->comment('法人');
            }
            if (!Schema::hasColumn('suppliers', 'registered_capital')) {
                $table->decimal('registered_capital', 14, 2)->nullable()->comment('注册资本');
            }
            if (!Schema::hasColumn('suppliers', 'website')) {
                $table->string('website', 200)->nullable()->comment('官网');
            }
            if (!Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name', 100)->nullable()->comment('开户行');
            }
            if (!Schema::hasColumn('suppliers', 'bank_account')) {
                $table->string('bank_account', 50)->nullable()->comment('银行账号');
            }
            if (!Schema::hasColumn('suppliers', 'account_name')) {
                $table->string('account_name', 100)->nullable()->comment('账户名');
            }
            if (!Schema::hasColumn('suppliers', 'tax_no')) {
                $table->string('tax_no', 50)->nullable()->comment('税号');
            }
            if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                $table->enum('payment_terms', ['cash', '30days', '60days', '90days'])
                    ->default('30days')->comment('账期');
            }
            if (!Schema::hasColumn('suppliers', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            }
            // 扩展 status 枚举：active/blacklisted -> active/paused/blacklist
        });

        // 扩展 status enum（PG 需要先 ADD 新枚举值，或用 text + check）
        // 简化：保留原 enum，新增 paused 值需要 drop+recreate，V0.4.2 直接放宽到 text
        try {
            DB::statement("ALTER TABLE suppliers ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("UPDATE suppliers SET status = 'active' WHERE status = 'blacklisted'");
        } catch (\Throwable $e) {
            // ignore - already text
        }
    }

    public function down(): void
    {
        // V0.4.2 不回滚 - V0.3.18 表继续存在
    }
};
