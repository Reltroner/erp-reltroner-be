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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key');
            $table->string('resource_key');
            $table->integer('max_value');
            $table->timestamps();

            $table->foreign('plan_key')->references('plan_key')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_key', 'resource_key']);
        });

        Schema::create('feature_entitlements', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key');
            $table->string('feature_key');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('plan_key')->references('plan_key')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_key', 'feature_key']);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('plan_key');
            $table->string('status')->default('active');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_key')->references('plan_key')->on('plans')->cascadeOnDelete();
            $table->unique('tenant_id');
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('resource_key');
            $table->integer('current_value')->default(0);
            $table->timestamp('reset_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'resource_key']);
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('user_profiles')->nullOnDelete();
            $table->string('resource_key');
            $table->integer('quantity')->default(1);
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('feature_entitlements');
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plans');
    }
};
