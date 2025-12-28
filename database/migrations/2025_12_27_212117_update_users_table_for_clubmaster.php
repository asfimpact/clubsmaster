<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the old name column if it exists
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }

            // Add our new specific columns
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('role')->default('user')->after('password');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
