<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove child notifications for duplicate order_id rows to avoid FK violations
        DB::statement('
            DELETE pn FROM payment_notifications pn
            INNER JOIN payment_requests p1 ON pn.payment_request_id = p1.id
            INNER JOIN payment_requests p2 ON p1.order_id = p2.order_id AND p1.id < p2.id
        ');

        // Remove duplicate order_id rows, keeping the one with the highest id (latest)
        DB::statement('
            DELETE p1 FROM payment_requests p1
            INNER JOIN payment_requests p2
            WHERE p1.id < p2.id AND p1.order_id = p2.order_id
        ');

        Schema::table('payment_requests', function (Blueprint $table) {
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
        });
    }
};
