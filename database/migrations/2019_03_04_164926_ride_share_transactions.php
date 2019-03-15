<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RideShareTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('RideSharingTransactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('driver_id')->references('id')->on('drivers');
            $table->unsignedInteger('taxi_id')->references('id')->on('taxis');
            $table->unsignedInteger('first_user_id')->references('id')->on('users');
            $table->unsignedInteger('second_user_id')->references('id')->on('users');
            $table->decimal('first_start_lat', 16, 8);
            $table->decimal('first_start_long', 16, 8);
            $table->text('first_start_addr');
            $table->decimal('first_des_lat', 16, 8);
            $table->decimal('first_des_long', 16, 8);
            $table->text('first_des_addr');
            $table->decimal('second_start_lat', 16, 8);
            $table->decimal('second_start_long', 16, 8);
            $table->text('second_start_addr');
            $table->decimal('second_des_lat', 16, 8);
            $table->decimal('second_des_long', 16, 8);
            $table->text('second_des_addr');
            $table->mediumText('requirement');
            $table->unsignedInteger('status');
            $table->time('meet_up_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('RideSharingTransactions');
    }
}
