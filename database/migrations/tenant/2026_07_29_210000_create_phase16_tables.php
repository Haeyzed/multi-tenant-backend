<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_families', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('product_family_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_set_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['attribute_set_id', 'attribute_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_family_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('attribute_set_id')->nullable()->after('product_family_id')->constrained()->nullOnDelete();
        });

        Schema::table('collections', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->after('is_active');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });

        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('converted_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');

        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attribute_set_id');
            $table->dropConstrainedForeignId('product_family_id');
        });

        Schema::dropIfExists('attribute_set_attributes');
        Schema::dropIfExists('attribute_sets');
        Schema::dropIfExists('product_families');
    }
};
