<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 深化施工上报 V1.1 - 工序验收 + 影像档案
 * 5 张表: process_templates / process_instances / process_inspections / process_images / process_signatures
 *
 * 核心业务:
 * - 工序模板按行业/子分类标准化(可复用)
 * - 项目实例化模板,跟踪施工进度
 * - 多级验收(自检/互检/监理/甲方)
 * - 影像档案支持 before/during/after/issue/acceptance 5 类
 * - 签字支持内部用户 + 外部业主(短信验证码)
 */
return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('process_templates')) {
            return;
        }

        // 1) 工序模板: 行业/子分类标准化,所有项目复用
        Schema::create('process_templates', function (Blueprint $table) {
            $table->id();
            $table->string('industry', 30)->comment('行业(security/building/transport/energy/industrial)');
            $table->string('category', 50)->comment('子分类(视频监控/门禁/报警/网络/弱电/...)');
            $table->string('code', 50)->comment('工序编码(SP001)');
            $table->string('name', 100)->comment('工序名称(线管敷设/线缆敷设/...)');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('standard_duration_days')->default(1)->comment('标准工期(天)');
            $table->decimal('standard_man_hours', 8, 2)->default(0)->comment('标准人工(工时)');
            $table->json('required_qualifications')->nullable()->comment('所需资质 [电工证,高空作业证]');
            $table->text('safety_requirements')->nullable()->comment('安全要求');
            $table->json('quality_checkpoints')->nullable()->comment('质量验收要点(checklist)');
            $table->json('acceptance_criteria')->nullable()->comment('验收标准');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['industry', 'code']);
            $table->index(['industry', 'category', 'is_active']);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // 2) 工序实例: 项目实际工序,从模板复制可改
        Schema::create('process_instances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->comment('项目ID');
            $table->unsignedBigInteger('template_id')->nullable()->comment('来源模板ID');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('前置工序ID(依赖)');
            $table->string('code', 50)->comment('实例编码(PRJ001-SP001-01)');
            $table->string('name', 100)->comment('工序名称');
            $table->unsignedSmallInteger('sequence')->default(0)->comment('工序顺序');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedSmallInteger('planned_duration_days')->default(0);
            $table->unsignedSmallInteger('actual_duration_days')->default(0);
            $table->string('status', 20)->default('pending')->comment('pending/in_progress/completed/accepted/rejected/blocked');
            $table->unsignedTinyInteger('progress')->default(0)->comment('0-100');
            $table->unsignedBigInteger('foreman_id')->nullable()->comment('施工员/工长');
            $table->json('workers')->nullable()->comment('工人列表 [{user_id, hours, days}]');
            $table->string('location', 200)->nullable()->comment('施工位置');
            $table->text('description')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedBigInteger('accepted_by')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('process_templates')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('process_instances')->onDelete('set null');
            $table->foreign('foreman_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('accepted_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'sequence']);
            $table->index(['status', 'planned_end_date']);
        });

        // 3) 工序验收: 自检/互检/监理/甲方 4 级
        Schema::create('process_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_instance_id');
            $table->string('inspection_type', 20)->comment('self/mutual/supervisor/owner');
            $table->unsignedBigInteger('inspector_id')->nullable()->comment('验收人(内部)');
            $table->string('inspector_name', 50)->nullable()->comment('验收人姓名(外部)');
            $table->date('inspection_date');
            $table->string('result', 20)->default('pending')->comment('pending/pass/fail/partial');
            $table->decimal('score', 5, 2)->nullable()->comment('评分 0-100');
            $table->json('checkpoint_results')->nullable()->comment('各检查点结果 {cp_id: {pass, remark}}');
            $table->json('issues')->nullable()->comment('不合格项列表');
            $table->text('suggestions')->nullable();
            $table->date('next_inspection_date')->nullable()->comment('复检日期');
            $table->json('image_ids')->nullable()->comment('关联影像IDs');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('process_instance_id')->references('id')->on('process_instances')->onDelete('cascade');
            $table->foreign('inspector_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['process_instance_id', 'inspection_type']);
            $table->index(['result', 'inspection_date']);
        });

        // 4) 工序影像: 5 类(before/during/after/issue/acceptance)
        Schema::create('process_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_instance_id');
            $table->unsignedBigInteger('inspection_id')->nullable()->comment('关联验收记录');
            $table->string('category', 20)->comment('before/during/after/issue/acceptance');
            $table->string('file_type', 10)->default('image')->comment('image/video');
            $table->string('file_name', 200);
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size')->default(0)->comment('字节');
            $table->string('mime_type', 50)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('视频时长(秒)');
            $table->string('thumbnail_path', 500)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->unsignedBigInteger('taken_by')->nullable();
            $table->string('location', 200)->nullable()->comment('拍摄地点描述');
            $table->json('geo')->nullable()->comment('GPS {lat, lng, accuracy}');
            $table->text('description')->nullable();
            $table->json('tags')->nullable()->comment('标签 [隐蔽工程,关键节点,...]');
            $table->timestamps();

            $table->foreign('process_instance_id')->references('id')->on('process_instances')->onDelete('cascade');
            $table->foreign('inspection_id')->references('id')->on('process_inspections')->onDelete('set null');
            $table->foreign('taken_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['process_instance_id', 'category']);
            $table->index(['inspection_id']);
            $table->index(['taken_at']);
        });

        // 5) 签字记录: 内部用户 + 外部业主(短信验证)
        Schema::create('process_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_instance_id');
            $table->unsignedBigInteger('inspection_id')->nullable();
            $table->string('signer_type', 20)->comment('contractor/owner/supervisor/inspector');
            $table->unsignedBigInteger('signer_id')->nullable()->comment('内部用户ID');
            $table->string('signer_name', 50)->comment('签字人姓名');
            $table->string('signer_phone', 20)->nullable()->comment('外部签字人手机号');
            $table->string('signer_role', 50)->nullable()->comment('职务:项目经理/业主代表/监理工程师');
            $table->longText('signature_data')->comment('签字笔迹(SVG/笔迹轨迹JSON)');
            $table->string('signature_image_path', 500)->nullable()->comment('签字图片');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 200)->nullable();
            $table->timestamp('signed_at');
            $table->timestamp('expires_at')->nullable()->comment('签字有效期');
            $table->string('verification_code', 10)->nullable()->comment('短信验证码');
            $table->boolean('is_verified')->default(false)->comment('是否短信验证');
            $table->string('hash', 64)->nullable()->comment('SHA256 防篡改哈希');
            $table->timestamps();

            $table->foreign('process_instance_id')->references('id')->on('process_instances')->onDelete('cascade');
            $table->foreign('inspection_id')->references('id')->on('process_inspections')->onDelete('set null');
            $table->foreign('signer_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['process_instance_id', 'signer_type']);
            $table->index(['signer_id']);
            $table->index(['signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_signatures');
        Schema::dropIfExists('process_images');
        Schema::dropIfExists('process_inspections');
        Schema::dropIfExists('process_instances');
        Schema::dropIfExists('process_templates');
    }
};
