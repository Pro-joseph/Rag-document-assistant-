<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->text('metadata')->nullable()->after('embedding');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE chunks ALTER COLUMN metadata TYPE jsonb USING metadata::jsonb');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chunks', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
