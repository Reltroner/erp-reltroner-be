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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('permission_key')->unique();
            $table->string('description');
            $table->string('module_key');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_key');
            $table->string('permission_key');
            $table->string('scope')->default('tenant');
            $table->timestamps();

            $table->foreign('permission_key')->references('permission_key')->on('permissions')->cascadeOnDelete();
            $table->unique(['role_key', 'permission_key']);
        });

        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_profile_id')->constrained('user_profiles')->cascadeOnDelete();
            $table->string('permission_key');
            $table->string('effect'); // ALLOW, DENY
            $table->timestamps();

            $table->foreign('permission_key')->references('permission_key')->on('permissions')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_profile_id', 'permission_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
