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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->enum('visibility', FileVisibilityEnum::values())->default(FileVisibilityEnum::Intern->value);
            $table->enum('category', FileCategoryEnum::values())->default(FileCategoryEnum::Other->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
