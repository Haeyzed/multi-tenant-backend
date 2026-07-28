<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('price_list_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->string('code')->nullable()->unique()->after('id');
            $table->foreignId('customer_group_id')->nullable()->after('code')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('credit_limit')->nullable()->after('company');
            $table->string('currency', 3)->nullable()->after('credit_limit');
            $table->boolean('tax_exempt')->default(false)->after('currency')->index();
            $table->string('tax_id')->nullable()->after('tax_exempt');
            $table->index(['customer_group_id', 'is_active']);
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('shipping')->index();
            $table->string('label')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('phone')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'type']);
        });

        Schema::create('customer_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_primary']);
        });

        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'customer_tag_id']);
        });

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('general')->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_addresses');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_group_id');
            $table->dropColumn(['code', 'credit_limit', 'currency', 'tax_exempt', 'tax_id']);
        });

        Schema::dropIfExists('customer_groups');
    }
};
