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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->integer('txn_status')->nullable();
            $table->string('txn_state')->nullable()->default('new');
            $table->float('amount')->nullable();
            $table->double('last_balance')->nullable();
            $table->string('payment_mode')->nullable();
            $table->integer('txn_id')->nullable();
            $table->timestamp('txn_time')->nullable();
            $table->float('txn_amount')->nullable();
            $table->string('context')->nullable();
            $table->float('exclusive_tax')->nullable();
            $table->unsignedBigInteger('policy_id');
            $table->integer('recurring_order_id')->references('id')->on('recurring_orders')->onDelete('cascade')->nullable();
            $table->integer('entity_id')->nullable();
            $table->integer('underwriter_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('recurring_order_id');
            $table->index('entity_id');
            $table->index('underwriter_id');

            // $table->enum('txn_state', ['new', 'pending' ,'success' , 'failed'])->nullable()->default('new');
            // $table->enum('payment_mode', ['cash', 'credit-debit' , 'wallet','prepaid','postpaid','cod','credit_card'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
