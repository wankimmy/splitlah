<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('public_token', 64)->unique();
            $table->string('organizer_name');
            $table->string('organizer_email')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->string('merchant_name')->nullable();
            $table->date('receipt_date')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('service_charge_cents')->default(0);
            $table->unsignedBigInteger('rounding_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->enum('split_mode', ['equal', 'manual', 'itemized', 'percentage'])->default('equal');
            $table->enum('tax_distribution', ['equal', 'proportional'])->nullable();
            $table->enum('rounding_mode', ['exact', 'nearest_005', 'nearest_010', 'nearest_100'])->default('exact');
            $table->enum('status', ['draft', 'published', 'closed'])->default('draft');
            $table->string('receipt_image_path')->nullable();
            $table->longText('ocr_raw_text')->nullable();
            $table->json('ocr_parsed_json')->nullable();
            $table->string('ocr_confidence')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->unsignedBigInteger('unit_price_cents')->nullable();
            $table->unsignedBigInteger('total_price_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('source', ['ocr', 'manual', 'system'])->default('manual');
            $table->boolean('is_fee')->default(false);
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('tax_share_cents')->default(0);
            $table->unsignedBigInteger('service_charge_share_cents')->default(0);
            $table->unsignedBigInteger('rounding_share_cents')->default(0);
            $table->bigInteger('adjustment_cents')->default(0);
            $table->decimal('percentage_share', 5, 2)->nullable();
            $table->json('breakdown_json')->nullable();
            $table->enum('status', ['unpaid', 'pending', 'paid', 'failed', 'cancelled', 'manual_paid'])->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_shared_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('share_cents')->default(0);
            $table->timestamps();
            $table->unique(['bill_item_id', 'participant_id']);
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->string('order_id', 32)->unique();
            $table->string('provider')->default('fiuu');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('MYR');
            $table->enum('status', ['created', 'pending', 'paid', 'failed', 'cancelled'])->default('created');
            $table->string('fiuu_tran_id')->nullable();
            $table->string('fiuu_channel')->nullable();
            $table->string('fiuu_appcode')->nullable();
            $table->string('fiuu_paydate')->nullable();
            $table->json('request_payload_json')->nullable();
            $table->json('response_payload_json')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('fiuu');
            $table->string('order_id')->nullable();
            $table->string('tran_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_valid_signature')->default(false);
            $table->json('payload_json');
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('actor_type')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('item_assignments');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
