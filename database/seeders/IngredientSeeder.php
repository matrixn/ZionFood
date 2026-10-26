<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(database_path('data/ingredients.json')), true, flags: JSON_THROW_ON_ERROR);
        // Preserve pre-publication imports when normalizing the source-key namespace.
        if (DB::table('ingredients')->whereNull('restaurant_id')->where('source_key', 'like', 'queen-%')->exists()) {
            foreach ($rows as $row) {
                DB::table('ingredients')->whereNull('restaurant_id')->where('source_key', 'queen-'.substr($row['source_key'], strlen('global-')))->update(['source_key' => $row['source_key']]);
            }
        }
        foreach ($rows as $row) {
            DB::table('ingredients')->insertOrIgnore([...$row, 'scope_key' => 'global', 'normalized_name' => Ingredient::normalized($row['name']), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
