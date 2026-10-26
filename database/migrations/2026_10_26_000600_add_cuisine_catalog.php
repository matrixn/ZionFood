<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuisines', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 100)->unique();
            $t->string('name_ro', 100);
            $t->string('name_en', 100);
            $t->text('description_ro');
            $t->text('description_en');
            $t->string('image_path', 500);
            $t->string('image_credit', 500)->nullable();
            $t->string('image_source_url', 1000)->nullable();
            $t->string('image_license', 150)->nullable();
            $t->string('image_license_url', 1000)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('location_cuisines', function (Blueprint $t) {
            $t->unsignedBigInteger('location_id');
            $t->unsignedBigInteger('organization_id');
            $t->foreign(['location_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurant_locations');
            $t->foreignId('cuisine_id')->constrained('cuisines');
            $t->timestamps();
            $t->primary(['location_id', 'cuisine_id']);
        });
        foreach (json_decode(file_get_contents(database_path('data/cuisines.json')), true, flags: JSON_THROW_ON_ERROR) as $row) {
            unset($row['topic']);
            DB::table('cuisines')->insert([...$row, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_cuisines');
        Schema::dropIfExists('cuisines');
    }
};
