<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable()->after('reported_user_id');
            $table->string('report_type')->default('user')->after('post_id'); // 'user' | 'post'

            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_reports', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropColumn(['post_id', 'report_type']);
        });
    }
};
