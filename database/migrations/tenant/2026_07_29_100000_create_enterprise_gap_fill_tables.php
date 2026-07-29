<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('url', 2048);
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id', 'lot_number']);
        });

        Schema::create('stock_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number')->unique();
            $table->string('status')->default('available');
            $table->foreignId('stock_ledger_entry_id')->nullable()->constrained('stock_ledger_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::table('stock_ledger_entries', function (Blueprint $table): void {
            $table->foreignId('stock_lot_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('serial_number')->nullable()->after('stock_lot_id');
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('counted_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('expected_quantity');
            $table->integer('counted_quantity')->nullable();
            $table->integer('variance')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
        });

        Schema::create('sales_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('sales_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['sales_payment_id', 'sales_invoice_id']);
            $table->index('sales_invoice_id');
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('currency_from', 3);
            $table->string('currency_to', 3);
            $table->decimal('rate', 18, 8);
            $table->dateTime('effective_at');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['currency_from', 'currency_to', 'effective_at']);
        });

        Schema::create('customer_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('customer_wallet_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('amount');
            $table->unsignedInteger('points')->default(0);
            $table->bigInteger('balance_after');
            $table->unsignedInteger('points_after');
            $table->nullableMorphs('reference');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->unsignedInteger('buy_quantity')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropColumn('buy_quantity');
        });

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('customer_wallet_ledgers');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('sales_payment_allocations');
        Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');

        Schema::table('stock_ledger_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_lot_id');
            $table->dropColumn('serial_number');
        });

        Schema::dropIfExists('stock_serials');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('product_media');
    }
};
