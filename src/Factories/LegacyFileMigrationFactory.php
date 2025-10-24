<?php

namespace Maxkhim\Dedupler\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maxkhim\Dedupler\Models\LegacyFileMigration;
use Maxkhim\Dedupler\Models\UniqueFile;

final class LegacyFileMigrationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LegacyFileMigration::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        // Генерируем случайные данные для файла

        Storage::disk("public")->makeDirectory("legacy");
        $legacyFileO = fake()->filePath();
        $legacyFile = basename($legacyFileO);
        $legacyFileMimeType = fake()->mimeType();
        Storage::disk("public")->put(
            "legacy/$legacyFileO",
            (string)mt_rand(100, 200)/* Str::random(60)*/
        );
        $sha1 = sha1(Storage::disk("public")->get("legacy/$legacyFileO"));

        return [
            'original_path' => pathinfo(Storage::disk("public")->path("legacy{$legacyFileO}"), PATHINFO_DIRNAME),
            'original_filename' => $legacyFile,
            'sha1_hash' => $sha1,
            'file_size' => Storage::disk("public")->size("legacy/$legacyFileO"),
            'mime_type' => $legacyFileMimeType,
            'status' => fake()->randomElement(['pending'/*, 'migrated', 'error', 'skipped'*/]),
            'has_duplicates' => 0,
            'migrated_at' => null,
            'migration_strategy' => 'link', //fake()->randomElement(['copy', 'move', 'link']),
            'file_modificated_at' => now(), //fake()->dateTimeBetween("-1 month")->format('Y-m-d H:i:s'),
            'created_at' => now(),//fake()->dateTimeBetween("-1 month")->format('Y-m-d H:i:s'),
            'updated_at' => now(),//fake()->dateTimeBetween("-1 month")->format('Y-m-d H:i:s'),
        ];
    }
}
