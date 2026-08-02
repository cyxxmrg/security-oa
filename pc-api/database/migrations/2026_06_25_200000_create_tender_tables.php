<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // V0.6.0 招标中心 — 4 张新表

        // 1) 招标项目 (Tender) — RFQ 升级版
        Schema::create('tender_projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('招标编号');
            $table->string('name', 200)->comment('招标项目名');
            $table->text('description')->nullable();
            // 关联
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->foreignId('rfq_id')->nullable()->constrained('external_quote_requests')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            // 类型/状态
            $table->string('type', 20)->default('tender')->comment('rfq/tender/negotiation');
            $table->string('status', 20)->default('draft')->comment('draft/published/bidding/evaluating/awarded/cancelled/closed');
            // 清单
            $table->jsonb('required_items')->nullable();
            // 邀请名单
            $table->jsonb('invited_supplier_ids')->nullable();
            // 时间
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('deadline')->nullable();
            $table->timestamp('open_at')->nullable()->comment('开标时间');
            // 公共链接 token (供供应商外部访问)
            $table->uuid('public_token')->unique();
            // 中标结果
            $table->foreignId('awarded_bid_id')->nullable();
            $table->foreignId('awarded_supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->timestamp('awarded_at')->nullable();
            // 附件 (招标文件/图纸) 走 tender_attachments
            // 评分配置
            $table->jsonb('score_config')->nullable()->comment('{technical: 40, price: 40, business: 20}');
            $table->timestamps();
            $table->index(['status', 'deadline']);
        });

        // 2) 投标 (Bid)
        Schema::create('tender_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_project_id')->constrained('tender_projects')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('code', 30)->nullable()->comment('投标编号');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->integer('lead_time_days')->nullable()->comment('工期(天)');
            $table->text('technical_proposal')->nullable();
            $table->text('remark')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft/submitted/shortlisted/awarded/rejected/withdrawn');
            $table->timestamp('submitted_at')->nullable();
            $table->jsonb('scores')->nullable()->comment('{technical: 80, price: 90, business: 85, total: 85}');
            $table->decimal('total_score', 6, 2)->nullable();
            $table->foreignId('submitter_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->unique(['tender_project_id', 'supplier_id']);
            $table->index('status');
        });

        // 3) 投标明细
        Schema::create('tender_bid_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_bid_id')->constrained('tender_bids')->onDelete('cascade');
            $table->string('name', 200);
            $table->string('spec', 200)->nullable();
            $table->string('unit', 20)->default('件');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 14, 2);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        // 4) 招标附件
        Schema::create('tender_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_project_id')->nullable()->constrained('tender_projects')->onDelete('cascade');
            $table->foreignId('tender_bid_id')->nullable()->constrained('tender_bids')->onDelete('cascade');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('uploaded_by_supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->string('file_name', 200);
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->bigInteger('file_size')->default(0);
            // 类别: technical/business/qualification/bid_file/tender_doc/drawing/other
            $table->string('category', 30)->default('other');
            // 可见性: public (招标方+投标方都能看) / eval_only (仅评标)
            $table->string('visibility', 20)->default('public');
            $table->timestamps();
            $table->index(['tender_project_id', 'category']);
        });

        // GRANT
        $tables = ['tender_projects', 'tender_bids', 'tender_bid_items', 'tender_attachments'];
        foreach ($tables as $t) {
            DB::statement("GRANT ALL PRIVILEGES ON TABLE {$t} TO oa_user");
            DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$t}_id_seq TO oa_user");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_attachments');
        Schema::dropIfExists('tender_bid_items');
        Schema::dropIfExists('tender_bids');
        Schema::dropIfExists('tender_projects');
    }
};
