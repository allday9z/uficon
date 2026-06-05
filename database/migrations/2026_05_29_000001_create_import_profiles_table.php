<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_profiles', function (Blueprint $table) {
            $table->id('ip_id');
            $table->string('ip_name');
            $table->string('ip_slug')->unique();
            $table->string('ip_sheet_name')->nullable()->comment('Target sheet name substring to match');
            $table->tinyInteger('ip_header_row')->default(1)->comment('Row number of the header (1-based)');
            $table->text('ip_description')->nullable();
            $table->timestamps();
        });

        Schema::create('import_column_maps', function (Blueprint $table) {
            $table->id('icm_id');
            $table->foreignId('ip_id')->constrained('import_profiles', 'ip_id')->cascadeOnDelete();
            $table->string('icm_source_header')->nullable()->comment('Column header name in the source file');
            $table->unsignedSmallInteger('icm_source_index')->nullable()->comment('0-based column index fallback');
            $table->string('icm_target_model')->comment('Product | ProductVariant | ProductCollection');
            $table->string('icm_target_field')->comment('DB column name on the target model');
            $table->string('icm_default_value')->nullable()->comment('Value to use when source cell is empty');
            $table->boolean('icm_required')->default(false)->comment('File must have this column to pass validation');
            $table->enum('icm_update_mode', ['always', 'create_only', 'skip'])
                  ->default('always')
                  ->comment('always=overwrite, create_only=INSERT only, skip=never write');
            $table->string('icm_cast')->nullable()->comment('Type cast: string|int|float|bool|slug|json');
            $table->unsignedSmallInteger('icm_position')->default(0)->comment('Display order in UI');
            $table->timestamps();

            $table->index('ip_id');
            $table->unique(['ip_id', 'icm_target_model', 'icm_target_field'], 'icm_profile_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_column_maps');
        Schema::dropIfExists('import_profiles');
    }
};
