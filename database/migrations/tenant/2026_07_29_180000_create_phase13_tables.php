<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('reorder_point')->nullable()->after('stock_quantity');
            $table->unsignedInteger('safety_stock')->nullable()->after('reorder_point');
            $table->unsignedInteger('min_stock')->nullable()->after('safety_stock');
            $table->unsignedInteger('max_stock')->nullable()->after('min_stock');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->unsignedInteger('reorder_point')->nullable()->after('quantity');
            $table->unsignedInteger('safety_stock')->nullable()->after('reorder_point');
            $table->unsignedInteger('min_stock')->nullable()->after('safety_stock');
            $table->unsignedInteger('max_stock')->nullable()->after('min_stock');
        });

        Schema::create('product_bundle_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bundle_product_id', 'component_product_id']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'is_primary']);
        });

        Schema::create('supplier_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('shipping');
            $table->string('label')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'type']);
        });

        Schema::table('return_authorizations', function (Blueprint $table): void {
            $table->text('inspection_notes')->nullable()->after('notes');
            $table->string('disposition')->nullable()->after('inspection_notes');
            $table->timestamp('inspected_at')->nullable()->after('received_at');
            $table->foreignId('inspected_by')->nullable()->after('inspected_at')->constrained('users')->nullOnDelete();
            $table->foreignId('replacement_order_id')->nullable()->after('credit_note_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('return_authorizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replacement_order_id');
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropColumn(['inspection_notes', 'disposition', 'inspected_at']);
        });

        Schema::dropIfExists('supplier_addresses');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('product_bundle_items');

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->dropColumn(['reorder_point', 'safety_stock', 'min_stock', 'max_stock']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['reorder_point', 'safety_stock', 'min_stock', 'max_stock']);
        });
    }
};
