<?php

use App\Models\MemberSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('sold_price', 10, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('expiration_notification_sent_for_date')->nullable();
            $table->text('qr_payload')->nullable();
            $table->timestamp('qr_generated_at')->nullable();
            $table->timestamp('qr_emailed_at')->nullable();
            $table->enum('status', [
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_EXPIRED,
                MemberSubscription::STATUS_CANCELLED,
                MemberSubscription::STATUS_PAUSED,
            ])->default(MemberSubscription::STATUS_ACTIVE);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
    }
};
