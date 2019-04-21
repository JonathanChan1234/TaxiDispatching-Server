<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Addcolumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sharerides', function (Blueprint $table) {
            $table->boolean('cancelled')->nullable();
            $table->dateTime('driverReachTime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sharerides', function (Blueprint $table) {
            $table->dropColumn('cancelled', 'driverReachTime');
        });
    }
}
