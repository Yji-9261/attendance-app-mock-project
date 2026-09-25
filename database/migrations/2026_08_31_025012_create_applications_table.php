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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->date('application_date');
            $table->time('new_clock_in');
            $table->time('new_clock_out');
            //$table->dateTime('application_date');
            //$table->dateTime('new_clock_in');
            //$table->dateTime('new_clock_out');
            $table->string('comment');
            $table->string('approval_status')->default('承認待ち');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
