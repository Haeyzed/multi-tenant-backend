<?php

declare(strict_types=1);

use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\StockReservationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('stock_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity'); // signed delta
            $table->unsignedInteger('quantity_after');
            $table->string('reason')->index();
            $table->nullableMorphs('reference');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['product_id', 'warehouse_id', 'created_at']);
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default(StockReservationStatus::Active->value)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        $now = now();

        DB::table('warehouse_stocks')
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($stocks) use ($now): void {
                foreach ($stocks as $stock) {
                    DB::table('stock_ledger_entries')->insert([
                        'warehouse_id' => $stock->warehouse_id,
                        'product_id' => $stock->product_id,
                        'quantity' => (int) $stock->quantity,
                        'quantity_after' => (int) $stock->quantity,
                        'reason' => StockMovementReason::OpeningBalance->value,
                        'reference_type' => null,
                        'reference_id' => null,
                        'notes' => 'Backfilled from warehouse_stocks',
                        'created_by' => null,
                        'created_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_ledger_entries');

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::dropIfExists('branches');
    }
};
