<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->string('title');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('payment_terms')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_agreement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('unit_cost')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->unique(['purchase_agreement_id', 'product_id'], 'purchase_agreement_product_unique');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('purchase_agreement_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->timestamp('manufactured_at')->nullable()->after('expires_at');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('upc')->nullable()->after('barcode')->index();
            $table->string('ean')->nullable()->after('upc')->index();
            $table->string('isbn')->nullable()->after('ean')->index();
            $table->string('qr_code')->nullable()->after('isbn');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['upc', 'ean', 'isbn', 'qr_code']);
        });

        Schema::table('stock_lots', function (Blueprint $table): void {
            $table->dropColumn('manufactured_at');
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_agreement_id');
        });

        Schema::dropIfExists('purchase_agreement_items');
        Schema::dropIfExists('purchase_agreements');
    }
};
