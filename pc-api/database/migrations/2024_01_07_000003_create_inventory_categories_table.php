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
        if (Schema::hasTable('inventory_categories')) {
            return;
        }

        // 1) 建分类表
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父分类ID');
            $table->foreign('parent_id')->references('id')->on('inventory_categories')->onDelete('cascade');
            $table->string('name', 100)->comment('分类名称');
            $table->string('code', 50)->nullable()->comment('分类编码');
            $table->unsignedInteger('sort_order')->default(0)->comment('排序');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();

            $table->index('parent_id');
        });

        // 2) 加 inventory_items.category_id 列
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('category')->comment('分类ID');
            $table->foreign('category_id')->references('id')->on('inventory_categories')->onDelete('set null');
            $table->index('category_id');
        });

        // 3) 数据迁移: 把旧 category 字符串去重插入新表
        $existing = DB::table('inventory_items')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');
        $sort = 0;
        $idMap = [];
        foreach ($existing as $catName) {
            $id = DB::table('inventory_categories')->insertGetId([
                'parent_id' => null,
                'name' => $catName,
                'code' => 'CAT-' . str_pad(++$sort, 3, '0', STR_PAD_LEFT),
                'sort_order' => $sort,
                'description' => '从旧字符串字段自动迁移',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $idMap[$catName] = $id;
        }

        // 4) 回填 items.category_id
        foreach ($idMap as $catName => $catId) {
            DB::table('inventory_items')
                ->where('category', $catName)
                ->update(['category_id' => $catId]);
        }

        // 5) 加一些常用的大类 (如果库里没有的话)
        $defaults = [
            ['name' => '监控设备', 'code' => 'CAT-MON', 'sort' => 1],
            ['name' => '报警设备', 'code' => 'CAT-ALM', 'sort' => 2],
            ['name' => '线缆辅材', 'code' => 'CAT-CAB', 'sort' => 3],
            ['name' => '工具仪表', 'code' => 'CAT-TOL', 'sort' => 4],
            ['name' => '备品备件', 'code' => 'CAT-SPA', 'sort' => 5],
            ['name' => '办公耗材', 'code' => 'CAT-OFF', 'sort' => 6],
        ];
        foreach ($defaults as $d) {
            $exists = DB::table('inventory_categories')->where('name', $d['name'])->exists();
            if (!$exists) {
                DB::table('inventory_categories')->insert([
                    'parent_id' => null,
                    'name' => $d['name'],
                    'code' => $d['code'],
                    'sort_order' => $d['sort'],
                    'description' => '系统预置分类',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('inventory_categories');
    }
};
