<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddConfirmColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rideshareTransactions', function (Blueprint $table) {
            $table->boolean('first_confirmed');
            $table->boolean('second_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rideshareTransactions', function (Blueprint $table) {
            $table->dropColumn('first_confirmed');
            $table->dropColumn('second_confirmed');
        });
    }
}
