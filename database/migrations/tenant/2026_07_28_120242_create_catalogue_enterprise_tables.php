<?php

declare(strict_types=1);

use App\Enums\Tenant\CollectionType;
use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('parent_id')->index();
            $table->string('image_url')->nullable()->after('description');
            $table->string('banner_url')->nullable()->after('image_url');
            $table->string('meta_title')->nullable()->after('banner_url');
            $table->string('meta_description')->nullable()->after('meta_title');
            $table->boolean('is_featured')->default(false)->after('is_active')->index();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('type')->default(ProductType::Simple->value)->after('category_id')->index();
            $table->string('status')->default(ProductStatus::Published->value)->after('type')->index();
            $table->foreignId('brand_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->after('brand_id')->constrained('products')->nullOnDelete();
            $table->foreignId('unit_of_measure_id')->nullable()->after('parent_id')->constrained('units_of_measure')->nullOnDelete();
            $table->string('slug')->nullable()->after('name');
            $table->string('gtin')->nullable()->after('sku')->index();
            $table->string('barcode')->nullable()->after('gtin')->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->boolean('track_inventory')->default(true)->after('stock_quantity');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
        });

        DB::table('products')->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                $base = Str::slug($product->name) ?: 'product-'.$product->id;
                $slug = $base;
                $i = 1;
                while (DB::table('products')->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                    $slug = $base.'-'.$i;
                    $i++;
                }

                DB::table('products')->where('id', $product->id)->update([
                    'slug' => $slug,
                    'type' => ProductType::Simple->value,
                    'status' => $product->is_active ? ProductStatus::Published->value : ProductStatus::Archived->value,
                    'track_inventory' => $product->stock_quantity !== null,
                    'published_at' => $product->is_active ? $product->created_at : null,
                ]);
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique('slug');
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'product_id']);
        });

        DB::table('products')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    DB::table('category_product')->insertOrIgnore([
                        'category_id' => $product->category_id,
                        'product_id' => $product->id,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_option_id', 'value']);
        });

        Schema::create('product_option_value_product', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_option_value_id']);
        });

        Schema::create('product_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type')->index(); // related|upsell|cross_sell|fbt
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'related_product_id', 'type']);
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default(CollectionType::Manual->value)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('collection_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['collection_id', 'product_id']);
        });

        Schema::create('collection_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->string('field'); // title|sku|type|status|brand_id|tag|price
            $table->string('operator'); // eq|neq|contains|gt|gte|lt|lte
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('input_type')->default('text'); // text|number|boolean|select
            $table->boolean('is_filterable')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_uoms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_of_measure_id')->constrained()->cascadeOnDelete();
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'unit_of_measure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('collection_rules');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('product_relations');
        Schema::dropIfExists('product_option_value_product');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('category_product');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropConstrainedForeignId('unit_of_measure_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn([
                'type', 'status', 'slug', 'gtin', 'barcode',
                'meta_title', 'meta_description', 'meta_keywords',
                'track_inventory', 'published_at', 'scheduled_at',
            ]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn([
                'sort_order', 'image_url', 'banner_url',
                'meta_title', 'meta_description', 'is_featured',
            ]);
        });

        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('brands');
    }
};
