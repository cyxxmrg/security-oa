<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->comment('客户');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->comment('跟进人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('type', 50)->default('visit')->comment('跟进方式');
            $table->text('content')->comment('跟进内容');
            $table->date('next_follow_up_date')->nullable()->comment('下次跟进日期');
            $table->string('next_follow_up_note', 500)->nullable()->comment('下次跟进说明');
            $table->json('attachments')->nullable()->comment('附件');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_records');
    }
};
