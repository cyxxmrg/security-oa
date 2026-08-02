<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // v0.3.18 幂等保护：v0.3.14 全量部署时已建表，重跑会报 42P07
        if (Schema::hasTable('approval_templates')) {
            return;
        }

        Schema::create('approval_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 200);
            $table->string('module', 50);
            $table->text('description')->nullable();
            $table->jsonb('nodes')->default('[]');
            $table->string('status', 20)->default('启用');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // 预置 7 个常用流程模板
        $seed = [
            ['日常请假审批', '请假', '标准请假流程', '启用', [
                ['name' => '发起申请', 'desc' => '员工提交请假申请', 'type' => 'start'],
                ['name' => '部门经理审批', 'desc' => '部门经理审核请假', 'type' => 'approval'],
                ['name' => '人事备案', 'desc' => '人事部门登记备案', 'type' => 'notify'],
                ['name' => '流程结束', 'desc' => '请假流程结束', 'type' => 'end'],
            ]],
            ['费用报销审批', '报销', '差旅/业务报销流程', '启用', [
                ['name' => '提交报销单', 'desc' => '员工提交费用报销', 'type' => 'start'],
                ['name' => '部门经理审核', 'desc' => '部门经理初审', 'type' => 'approval'],
                ['name' => '财务审核', 'desc' => '财务部门复核票据', 'type' => 'approval'],
                ['name' => '总经理审批', 'desc' => '超出限额需总经理审批', 'type' => 'condition'],
                ['name' => '财务打款', 'desc' => '流程结束', 'type' => 'end'],
            ]],
            ['出差申请审批', '出差', '出差申请流程', '启用', [
                ['name' => '出差申请', 'desc' => '员工提交出差申请', 'type' => 'start'],
                ['name' => '部门审批', 'desc' => '部门经理审批', 'type' => 'approval'],
                ['name' => '总经理审批', 'desc' => '总经理审批', 'type' => 'approval'],
                ['name' => '流程结束', 'desc' => '出差流程结束', 'type' => 'end'],
            ]],
            ['采购审批流程', '采购', '采购审批流程', '启用', [
                ['name' => '采购申请', 'desc' => '发起采购', 'type' => 'start'],
                ['name' => '部门审批', 'desc' => '部门审批', 'type' => 'approval'],
                ['name' => '采购部询比价', 'desc' => '采购部比价', 'type' => 'approval'],
                ['name' => '财务审核', 'desc' => '财务审核', 'type' => 'approval'],
                ['name' => '总经理审批', 'desc' => '总经理审批', 'type' => 'approval'],
                ['name' => '采购执行', 'desc' => '采购下单', 'type' => 'end'],
            ]],
            ['合同审批流程', '合同', '合同审批流程', '启用', [
                ['name' => '发起合同', 'desc' => '提交合同申请', 'type' => 'start'],
                ['name' => '法务审核', 'desc' => '法务审核条款', 'type' => 'approval'],
                ['name' => '财务审核', 'desc' => '财务审核', 'type' => 'approval'],
                ['name' => '总经理审批', 'desc' => '总经理审批', 'type' => 'approval'],
                ['name' => '流程结束', 'desc' => '合同签订完成', 'type' => 'end'],
            ]],
            ['加班审批流程', '请假', '加班申请流程', '停用', [
                ['name' => '加班申请', 'desc' => '员工提交加班', 'type' => 'start'],
                ['name' => '主管审批', 'desc' => '直接主管审批', 'type' => 'approval'],
                ['name' => '流程结束', 'desc' => '加班流程结束', 'type' => 'end'],
            ]],
            ['项目立项审批', '采购', '项目立项流程', '启用', [
                ['name' => '立项申请', 'desc' => '项目立项申请', 'type' => 'start'],
                ['name' => '部门评估', 'desc' => '部门评估可行性', 'type' => 'approval'],
                ['name' => '技术评审', 'desc' => '技术方案评审', 'type' => 'approval'],
                ['name' => '财务评审', 'desc' => '财务预算评审', 'type' => 'approval'],
                ['name' => '总经理审批', 'desc' => '总经理审批', 'type' => 'approval'],
                ['name' => '立项通知', 'desc' => '通知所有相关方', 'type' => 'notify'],
                ['name' => '流程结束', 'desc' => '立项完成', 'type' => 'end'],
            ]],
        ];
        foreach ($seed as [$name, $module, $desc, $status, $nodes]) {
            DB::table('approval_templates')->insert([
                'name' => $name, 'module' => $module, 'description' => $desc,
                'nodes' => json_encode($nodes, JSON_UNESCAPED_UNICODE),
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_templates');
    }
};
