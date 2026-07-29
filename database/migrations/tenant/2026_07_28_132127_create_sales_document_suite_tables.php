<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_fulfilled')->default(0)->after('quantity');
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->index();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('valid_until')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();

            $table->index(['quotation_id', 'product_id']);
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('general')->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'type']);
        });

        Schema::create('fulfilments', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::create('fulfilment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulfilment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['fulfilment_id', 'order_item_id']);
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('fulfilment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shipment_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3)->index();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sales_invoice_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('credit_note_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('shipment_packages');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('fulfilment_items');
        Schema::dropIfExists('fulfilments');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('quantity_fulfilled');
        });
    }
};
