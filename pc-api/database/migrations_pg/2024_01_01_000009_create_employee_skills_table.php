<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_profile_id')->comment('员工档案');
            $table->foreign('employee_profile_id')->references('id')->on('employee_profiles')->onDelete('cascade');
            $table->unsignedBigInteger('skill_tag_id')->comment('技能标签');
            $table->foreign('skill_tag_id')->references('id')->on('skill_tags')->onDelete('cascade');
            $table->string('proficiency', 50)->default('intermediate')->comment('熟练度');
            $table->timestamps();

            $table->unique(['employee_profile_id', 'skill_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
