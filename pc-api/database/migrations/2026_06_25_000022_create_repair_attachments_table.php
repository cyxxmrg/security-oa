<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('repair_attachments')) {
            return;
        }

        Schema::create('repair_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('repair_order_id')->index();
            $table->string('file_path', 255);
            $table->string('file_name', 128);
            $table->string('file_type', 16)->default('image')->comment('image/pdf/other');
            $table->string('category', 32)->default('other')
                ->comment('received/process/repaired/shipping_receipt/other');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_attachments');
    }
};
