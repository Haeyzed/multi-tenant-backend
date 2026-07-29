<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_packages', function (Blueprint $table): void {
            $table->string('label_provider')->nullable()->after('label');
            $table->string('label_url')->nullable()->after('label_provider');
            $table->json('label_payload')->nullable()->after('label_url');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_packages', function (Blueprint $table): void {
            $table->dropColumn(['label_provider', 'label_url', 'label_payload']);
        });
    }
};
