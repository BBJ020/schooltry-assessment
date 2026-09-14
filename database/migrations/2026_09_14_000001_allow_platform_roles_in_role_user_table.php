<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropPrimary(['school_id', 'role_id', 'user_id']);
            $table->dropForeign(['school_id']);
            $table->unsignedBigInteger('school_id')->nullable()->change();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->unique(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropUnique(['role_id', 'user_id']);
            $table->dropForeign(['school_id']);
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->primary(['school_id', 'role_id', 'user_id']);
        });
    }
};
