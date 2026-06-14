<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (! Schema::hasColumn('organizations', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('website');
            }

            if (! Schema::hasColumn('organizations', 'iban')) {
                $table->string('iban')->nullable()->after('bank_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            if (Schema::hasColumn('organizations', 'iban')) {
                $table->dropColumn('iban');
            }

            if (Schema::hasColumn('organizations', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
        });
    }
};
