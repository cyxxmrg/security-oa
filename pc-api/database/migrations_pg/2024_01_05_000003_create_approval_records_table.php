<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 100)->comment('多态类型');
            $table->unsignedBigInteger('approvable_id')->comment('多态ID');
            $table->unsignedBigInteger('user_id')->comment('审批人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('action', 50);
            $table->text('comment')->nullable()->comment('审批意见');
            $table->string('status', 50);
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};
