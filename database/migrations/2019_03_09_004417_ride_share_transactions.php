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
        Schema::create('RideShareTransactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('first_transaction')->references('id')->on('transaction');
            $table->unsignedInteger('second_transaction')->references('id')->on('transaction');
            $table->unsignedInteger('driver_id')->references('id')->on('drivers');
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
        Schema::dropIfExists('RideShareTransactions');
    }
}
