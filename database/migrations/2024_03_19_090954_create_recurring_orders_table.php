<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_request_id');
            $table->unsignedBigInteger('entity_id');
            $table->integer('attempts')->defaults(0);
            $table->string('progress')->nullable();
            $table->date('execution_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('execution_date');
            $table->index('progress');
            $table->index('recurring_request_id');
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_orders');
    }
};
