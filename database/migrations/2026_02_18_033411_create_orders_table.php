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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('workflow_id')->unique();

            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('delivery_address');
            // money fields intentionally omitted for simplicity
            // order status will be tracked in Temporal
            // product list also omitted for simplicity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
