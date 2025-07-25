<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->text('payment_intent')->nullable();
            $table->text('payment_method')->nullable();
            $table->text('receipt_url')->nullable();
            $table->text('receipt_email')->nullable();
            $table->text('latest_charge')->nullable();
            $table->string('expires_at')->nullable();
            $table->double('amount');
            $table->double('tax')->nullable();
            $table->text("refund_id")->nullable();
            $table->text("balance_transaction")->nullable();
            $table->string("refunded_at")->nullable();
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
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
