<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_rfq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['supplier_rfq_id', 'supplier_id']);
        });

        Schema::create('supplier_quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('line_total');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('pin')->nullable();
            $table->unsignedBigInteger('balance_initial');
            $table->unsignedBigInteger('balance_remaining');
            $table->string('currency', 3);
            $table->string('status')->default('active')->index();
            $table->foreignId('issued_to')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('gift_card_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_redemptions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('supplier_quote_items');
        Schema::dropIfExists('supplier_quotes');
        Schema::dropIfExists('supplier_rfq_items');
        Schema::dropIfExists('supplier_rfqs');
    }
};
