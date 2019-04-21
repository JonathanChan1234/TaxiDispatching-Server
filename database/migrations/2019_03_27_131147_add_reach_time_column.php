<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReachTimeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ridesharetransactions', function (Blueprint $table) {
            $table->dateTime('firstReachTime');
            $table->dateTime('secondReachTime');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ridesharetransactions', function (Blueprint $table) {
            $table->dropColumn('firstreachtime');
            $table->dropColumn('secondreachtime');
        });
    }
}
