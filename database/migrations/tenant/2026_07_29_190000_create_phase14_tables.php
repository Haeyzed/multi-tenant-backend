<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('type')->nullable()->after('customer_group_id')->index();
            $table->string('payment_terms')->nullable()->after('credit_limit');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('parent_order_id')->nullable()->after('pos_session_id')->constrained('orders')->nullOnDelete();
            $table->index('parent_order_id');
        });

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->unsignedInteger('damaged_quantity')->default(0)->after('max_stock');
            $table->unsignedInteger('on_hold_quantity')->default(0)->after('damaged_quantity');
        });

        Schema::create('supplier_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('supplier_group_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_group_id');
        });

        Schema::dropIfExists('supplier_groups');

        Schema::table('warehouse_stocks', function (Blueprint $table): void {
            $table->dropColumn(['damaged_quantity', 'on_hold_quantity']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_order_id');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['type', 'payment_terms']);
        });
    }
};
