<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTranscationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transcations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('driver_id');
            $table->double('start_lat', 8, 8);
            $table->double('start_long', 8, 8);
            $table->text('start_addr');
            $table->double('des_lat', 8, 8);
            $table->double('des_long', 8, 8);
            $table->text('des_addr');
            $table->mediumText('requirement');
            $table->unsignedInteger('first_driver_id');
            $table->unsignedInteger('second_driver_id');
            $table->unsignedInteger('third_driver_id');
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
        Schema::dropIfExists('transcations');
    }
}
