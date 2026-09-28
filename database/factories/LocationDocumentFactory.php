<?php

namespace Database\Factories;

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use App\Models\LocationDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationDocument>
 */
class LocationDocumentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->slug(2).'.pdf';

        return [
            'location_id'  => \App\Models\CustomerLocation::factory(),
            'uploaded_by'  => User::factory()->administrator(),
            'title'        => fake()->randomElement(['Grundriss', 'Objektschlüssel-Übergabe', 'Zugangsplan', 'Reingangsliste']),
            'file_path'    => 'location-documents/'.$name,
            'original_name'=> $name,
            'mime_type'    => 'application/pdf',
            'size_bytes'   => fake()->numberBetween(20_000, 4_000_000),
            'visibility'   => FileVisibilityEnum::Intern->value,
            'category'     => FileCategoryEnum::Other->value,
        ];
    }

    public function intern(): static
    {
        return $this->state(fn() => ['visibility' => FileVisibilityEnum::Intern->value]);
    }

    public function extern(): static
    {
        return $this->state(fn() => ['visibility' => FileVisibilityEnum::Extern->value]);
    }

    public function category(FileCategoryEnum|string $category): static
    {
        return $this->state(fn() => [
            'category' => $category instanceof FileCategoryEnum ? $category->value : $category,
        ]);
    }

    public function image(): static
    {
        $name = fake()->unique()->slug(2).'.jpg';

        return $this->state(fn() => [
            'file_path'     => 'location-documents/'.$name,
            'original_name' => $name,
            'mime_type'     => 'image/jpeg',
        ]);
    }
}
