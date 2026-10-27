<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $t) {
            $t->json('order_types')->nullable();
            $t->string('kitchen_name', 150)->nullable();
            $t->string('source_key', 190)->nullable();
        });
        Schema::table('menu_item_variants', function (Blueprint $t) {
            $t->boolean('is_default')->default(false);
            $t->json('nutrition')->nullable();
        });
        Schema::table('modifier_groups', function (Blueprint $t) {
            $t->boolean('is_required')->nullable();
            $t->boolean('allow_quantity')->default(false);
        });
        Schema::table('modifier_options', function (Blueprint $t) {
            $t->boolean('is_default')->default(false);
            $t->unsignedSmallInteger('max_quantity')->default(50);
            $t->string('kitchen_name', 150)->nullable();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->string('kitchen_name', 150)->nullable());
        Schema::table('order_item_modifiers', function (Blueprint $t) {
            $t->unsignedSmallInteger('quantity')->default(1);
            $t->string('kitchen_name', 150)->nullable();
        });
        foreach (['menu_category_modifier_groups' => 'category_id', 'menu_variant_modifier_groups' => 'variant_id'] as $name => $column) {
            Schema::create($name, function (Blueprint $t) use ($column) {
                $t->id();
                $t->foreignId('organization_id')->constrained();
                $t->foreignId($column)->constrained($column === 'category_id' ? 'menu_categories' : 'menu_item_variants')->cascadeOnDelete();
                $t->foreignId('group_id')->constrained('modifier_groups');
                $t->unique([$column, 'group_id']);
            });
        }
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('organization_id')->constrained();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->foreignId('user_id')->constrained();
            $t->string('provider')->default('gloriafood');
            $t->string('source_restaurant')->nullable();
            $t->string('status')->default('queued');
            $t->text('credential')->nullable();
            $t->json('settings');
            $t->string('failure_code')->nullable();
            $t->timestamps();
        });
        Schema::create('import_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->unique()->constrained('import_batches')->cascadeOnDelete();
            $t->longText('source');
            $t->longText('canonical');
            $t->string('checksum', 64);
            $t->timestamps();
        });
        Schema::create('import_issues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->string('severity');
            $t->string('code');
            $t->string('path', 250);
            $t->text('message');
            $t->timestamps();
        });
        Schema::create('import_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->unique()->constrained('import_batches')->cascadeOnDelete();
            $t->json('report');
            $t->timestamps();
        });
        Schema::create('import_entity_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('restaurant_locations');
            $t->string('namespace');
            $t->string('entity_type');
            $t->string('external_id', 190);
            $t->unsignedBigInteger('local_id');
            $t->unique(['batch_id', 'entity_type', 'external_id'], 'import_mapping_unique');
        });
        Schema::create('import_publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->unique()->constrained('import_batches');
            $t->foreignId('menu_id')->constrained('menus');
            $t->json('previous_menu_ids');
            $t->json('product_hashes');
            $t->string('published_hash', 64);
            $t->foreignId('approved_by')->constrained('users');
            $t->timestamp('reverted_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_publications', 'import_entity_mappings', 'import_reports', 'import_issues', 'import_snapshots', 'import_batches', 'menu_variant_modifier_groups', 'menu_category_modifier_groups'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('menu_items', fn (Blueprint $t) => $t->dropColumn(['order_types', 'kitchen_name', 'source_key']));
        Schema::table('menu_item_variants', fn (Blueprint $t) => $t->dropColumn(['is_default', 'nutrition']));
        Schema::table('modifier_groups', fn (Blueprint $t) => $t->dropColumn(['is_required', 'allow_quantity']));
        Schema::table('modifier_options', fn (Blueprint $t) => $t->dropColumn(['is_default', 'max_quantity', 'kitchen_name']));
        Schema::table('order_item_modifiers', fn (Blueprint $t) => $t->dropColumn(['quantity', 'kitchen_name']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('kitchen_name'));
    }
};
