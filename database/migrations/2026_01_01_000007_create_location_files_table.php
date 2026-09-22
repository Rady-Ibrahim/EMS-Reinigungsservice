<?php

use App\Enums\FileCategoryEnum;
use App\Enums\FileVisibilityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('customer_locations')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');

            $table->enum('visibility', FileVisibilityEnum::values())
                  ->default(FileVisibilityEnum::Intern->value);

            $table->enum('category', FileCategoryEnum::values())
                  ->default(FileCategoryEnum::Other->value);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_files');
    }
};
