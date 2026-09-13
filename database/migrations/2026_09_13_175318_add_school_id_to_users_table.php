<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->restrictOnDelete();

            $table->boolean('is_active')
                ->default(true)
                ->after('password');

            $table->index(['school_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'email']);
            $table->dropForeign(['school_id']);
            $table->dropColumn(['school_id', 'is_active']);
        });
    }
};
