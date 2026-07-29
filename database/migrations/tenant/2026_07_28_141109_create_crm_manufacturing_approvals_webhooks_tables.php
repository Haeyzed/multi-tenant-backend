<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('source')->nullable()->index();
            $table->string('status')->default('new')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('estimated_value')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'owner_id']);
        });

        Schema::create('opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('name');
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage')->default('qualification')->index();
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->timestamp('expected_close_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('note')->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->nullableMorphs('subjectable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bill_of_materials', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'version']);
        });

        Schema::create('bill_of_material_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_of_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['bill_of_material_id', 'component_product_id'], 'bom_component_unique');
        });

        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('bill_of_material_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('work_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity_required');
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->timestamps();

            $table->unique(['work_order_id', 'component_product_id'], 'work_order_component_unique');
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('type')->index();
            $table->morphs('approvable');
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('request_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event')->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'status']);
        });

        Schema::create('data_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('type')->index();
            $table->string('resource')->index();
            $table->string('status')->default('pending')->index();
            $table->json('options')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_jobs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('work_order_items');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('bill_of_material_items');
        Schema::dropIfExists('bill_of_materials');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('leads');
    }
};
