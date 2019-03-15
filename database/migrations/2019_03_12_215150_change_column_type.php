<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeColumnType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ridesharetransactions', function (Blueprint $table) {
            $table->integer('first_confirmed')->change();
            $table->integer('second_confirmed')->change();
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
