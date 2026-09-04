<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('role')->default('member')->after('password');
            $table->string('google_id')->nullable()->unique()->after('role');
        });

        DB::statement('CREATE INDEX users_role_idx ON users (role)');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'google_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
