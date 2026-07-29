<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('web')->index();
            $table->string('adapter')->nullable()->index();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->json('config')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('channel_inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('buffer_quantity')->default(0);
            $table->unsignedInteger('published_quantity')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();

            $table->unique(['channel_id', 'product_id', 'warehouse_id'], 'channel_inventory_unique');
            $table->index(['channel_id', 'is_published']);
        });

        Schema::create('channel_product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('unit_price');
            $table->string('currency', 3)->index();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();

            $table->unique(['channel_id', 'product_id', 'min_quantity'], 'channel_product_price_unique');
        });

        Schema::create('pos_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('opening_float')->default(0);
            $table->unsignedBigInteger('closing_float')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('channel_id')->nullable()->after('warehouse_id')->constrained()->nullOnDelete();
            $table->foreignId('pos_session_id')->nullable()->after('channel_id')->constrained()->nullOnDelete();
            $table->index(['channel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropConstrainedForeignId('channel_id');
        });

        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('channel_product_prices');
        Schema::dropIfExists('channel_inventories');
        Schema::dropIfExists('channels');
    }
};
