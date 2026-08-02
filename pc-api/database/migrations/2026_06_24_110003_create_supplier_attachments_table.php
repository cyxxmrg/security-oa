<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V0.4.2 供应商附件（营业执照/合同/资质等）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_attachments')) {
            return;
        }

        Schema::create('supplier_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->enum('type', ['license', 'contract', 'certificate', 'bank', 'other'])
                ->default('other')->comment('附件类型');
            $table->string('name', 200)->comment('附件名');
            $table->string('file_path', 500)->comment('文件路径');
            $table->unsignedBigInteger('file_size')->default(0)->comment('文件大小(字节)');
            $table->string('mime_type', 100)->nullable()->comment('MIME');
            $table->date('expire_date')->nullable()->comment('到期日（资质类）');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('上传人');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_attachments');
    }
};
