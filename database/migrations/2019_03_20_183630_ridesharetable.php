<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Ridesharetable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sharerides', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('rideshare_id')->references('id')->on('ridesharetransactions');
            $table->unsignedInteger('user_id')->references('id')->on('users');
            $table->decimal('start_lat', 16, 8);
            $table->decimal('start_long', 16, 8);
            $table->text('start_addr');
            $table->decimal('des_lat', 16, 8);
            $table->decimal('des_long', 16, 8);
            $table->text('des_addr');
            $table->unsignedInteger('status');
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
        Schema::dropIfExists('sharerides');
    }
}
