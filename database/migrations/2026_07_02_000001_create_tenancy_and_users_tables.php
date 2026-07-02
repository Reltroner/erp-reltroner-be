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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('status')->default('active'); // active, suspended
            $table->string('plan_key')->default('free');
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('keycloak_subject')->unique();
            $table->string('email')->unique();
            $table->string('display_name');
            $table->string('status')->default('active'); // active, suspended
            $table->timestamps();
        });

        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_profile_id')->constrained('user_profiles')->cascadeOnDelete();
            $table->string('role_key'); // owner, manager, finance, warehouse, sales, viewer
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_profile_id']);
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_profile_id')->constrained('user_profiles')->cascadeOnDelete();
            $table->string('admin_role'); // super-admin, erp-admin
            $table->string('status')->default('active');
            $table->foreignId('granted_by')->nullable()->constrained('user_profiles')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_profile_id', 'admin_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('tenant_memberships');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('tenants');
    }
};
