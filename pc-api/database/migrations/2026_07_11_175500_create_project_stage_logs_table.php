<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_stage_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('project_id');
            $t->string('stage_key', 40);
            $t->string('action', 20)->default('enter');
            $t->string('note', 2000)->nullable();
            $t->unsignedBigInteger('entered_by')->nullable();
            $t->timestamps();
            $t->index(['project_id', 'stage_key']);
            $t->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $t->foreign('entered_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stage_logs');
    }
};