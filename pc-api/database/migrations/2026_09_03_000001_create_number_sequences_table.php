<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('number_sequences')) {
            return;
        }

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('scope', 150)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
