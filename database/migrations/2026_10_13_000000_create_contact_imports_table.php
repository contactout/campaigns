<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->json('mapping');
            $table->string('duplicate_action', 16);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->json('failures')->nullable();
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedInteger('rows_read')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'user_id', 'created_at']);
        });

        Schema::create('contact_import_emails', function (Blueprint $table) {
            $table->foreignId('contact_import_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->unsignedInteger('row');

            $table->unique(['contact_import_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_import_emails');
        Schema::dropIfExists('contact_imports');
    }
};
