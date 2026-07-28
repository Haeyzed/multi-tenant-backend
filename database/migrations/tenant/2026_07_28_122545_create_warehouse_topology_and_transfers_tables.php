<?php

declare(strict_types=1);

use App\Enums\Tenant\WarehouseTransferStatus;
use App\Enums\Tenant\WarehouseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('type')->default(WarehouseType::Standard->value)->after('code')->index();
            $table->foreignId('manager_user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('warehouse_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index('name');
        });

        Schema::create('warehouse_bins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'code']);
            $table->index('name');
        });

        Schema::create('stock_adjustment_reasons', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('increases_stock')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('warehouse_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status')->default(WarehouseTransferStatus::Draft->value)->index();
            $table->text('notes')->nullable();
            $table->text('dispatch_notes')->nullable();
            $table->unsignedInteger('transfer_cost')->default(0);
            $table->string('currency', 3)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_warehouse_id', 'status']);
            $table->index(['destination_warehouse_id', 'status']);
        });

        Schema::create('warehouse_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->default(0);
            $table->foreignId('source_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->foreignId('destination_bin_id')->nullable()->constrained('warehouse_bins')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
        Schema::dropIfExists('warehouse_transfers');
        Schema::dropIfExists('stock_adjustment_reasons');
        Schema::dropIfExists('warehouse_bins');
        Schema::dropIfExists('warehouse_zones');

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_user_id');
            $table->dropColumn('type');
        });
    }
};
