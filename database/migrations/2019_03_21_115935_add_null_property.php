<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNullProperty extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ridesharetransactions', function (Blueprint $table) {
            $table->unsignedInteger("driver_id")->nullable(true)->change();
            $table->unsignedInteger("taxi_id")->nullable(true)->change();
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
            //
        });
    }
}
