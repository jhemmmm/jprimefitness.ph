<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->string('pay_frequency', 20)->nullable();
            $table->string('photo_url')->nullable();
            $table->string('address')->nullable();
            $table->enum('status', [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])->default(User::STATUS_ACTIVE);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('sold_price', 10, 2)->default(0);
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('manager_commission_rate', 5, 2)->default(0);
            $table->decimal('manager_commission_amount', 10, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('expiration_notification_sent_for_date')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled', 'paused'])->default('active');
            $table->string('manager_commission_status')->default('unassigned');
            $table->dateTime('manager_commission_earned_at')->nullable();
            $table->foreignId('commission_payroll_id')->nullable();
            $table->timestamps();

            $table->index(
                ['manager_id', 'manager_commission_status'],
                'member_subscriptions_manager_commission_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
