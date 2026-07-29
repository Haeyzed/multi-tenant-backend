<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('tracking_url_template')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->json('countries')->nullable();
            $table->json('postal_codes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedBigInteger('rate')->default(0);
            $table->string('currency', 3)->default('USD')->index();
            $table->unsignedBigInteger('min_order_total')->nullable();
            $table->unsignedBigInteger('max_order_total')->nullable();
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shipping_carrier_id', 'code']);
            $table->index(['shipping_zone_id', 'is_active']);
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('shipping_carrier_id')->nullable()->after('carrier')->constrained()->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->after('shipping_carrier_id')->constrained()->nullOnDelete();
        });

        Schema::create('return_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('return_authorization_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('return_authorization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->boolean('restock')->default(true);
            $table->timestamps();

            $table->index(['return_authorization_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_authorization_items');
        Schema::dropIfExists('return_authorizations');

        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropConstrainedForeignId('shipping_carrier_id');
        });

        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_carriers');
    }
};
