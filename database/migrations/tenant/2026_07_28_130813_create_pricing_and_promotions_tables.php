<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('currency', 3)->index();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index(['currency', 'is_default', 'is_active']);
        });

        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'min_quantity']);
            $table->index(['product_id', 'min_quantity']);
        });

        Schema::create('price_list_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->string('assignable_type')->index();
            $table->unsignedBigInteger('assignable_id');
            $table->timestamps();

            $table->unique(['price_list_id', 'assignable_type', 'assignable_id'], 'price_list_assignment_unique');
            $table->index(['assignable_type', 'assignable_id']);
        });

        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->foreign('price_list_id')
                ->references('id')
                ->on('price_lists')
                ->nullOnDelete();
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->index();
            $table->unsignedBigInteger('value');
            $table->string('currency', 3)->nullable()->index();
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedBigInteger('min_subtotal')->nullable();
            $table->boolean('stackable')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('promotion_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'product_id']);
        });

        Schema::create('promotion_customer_group', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'customer_group_id'], 'promotion_customer_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_customer_group');
        Schema::dropIfExists('promotion_product');
        Schema::dropIfExists('promotions');

        Schema::table('customer_groups', function (Blueprint $table): void {
            $table->dropForeign(['price_list_id']);
        });

        Schema::dropIfExists('price_list_assignments');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
