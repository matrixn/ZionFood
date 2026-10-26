<?php

use Database\Seeders\IngredientSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->unsignedBigInteger('restaurant_id')->nullable();
            $t->foreign(['restaurant_id', 'organization_id'])->references(['id', 'organization_id'])->on('restaurants');
            $t->string('scope_key', 60);
            $t->string('source_key', 120)->nullable()->unique();
            $t->string('name', 150);
            $t->string('name_en', 150)->nullable();
            $t->string('normalized_name', 150);
            $t->unique(['scope_key', 'normalized_name']);
            $t->boolean('contains_allergens')->default(false);
            $t->boolean('from_frozen')->default(false);
            $t->boolean('is_active')->default(true);
            $t->boolean('needs_review')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('menu_item_ingredients', function (Blueprint $t) {
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('organization_id');
            $t->foreign(['item_id', 'organization_id'])->references(['id', 'organization_id'])->on('menu_items');
            $t->foreignId('ingredient_id')->constrained('ingredients');
            $t->unsignedSmallInteger('position')->default(0);
            $t->primary(['item_id', 'ingredient_id']);
        });
        Schema::table('menu_items', function (Blueprint $t) {
            $t->string('portion_size', 50)->nullable();
            $t->boolean('contains_allergens')->default(false);
            $t->boolean('from_frozen')->default(false);
        });
        (new IngredientSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_ingredients');
        Schema::dropIfExists('ingredients');
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn(['portion_size', 'contains_allergens', 'from_frozen']));
    }
};
