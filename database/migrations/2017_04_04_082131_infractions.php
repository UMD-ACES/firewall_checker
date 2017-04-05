<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Infractions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Actual Infractions committed
        Schema::create('infractions', function(Blueprint $table)
        {
           $table->increments('id');
           $table->integer('group_id');
           $table->string('inserted_by');
           $table->integer('type');
           $table->integer('period');
           $table->timestamp('inserted_on');
        });

        //Table for the type of infractions
        Schema::create('infractions_type', function(Blueprint $table)
        {
           $table->increments('id');
           $table->string('name');
           $table->bigInteger('grace_seconds');
           $table->integer('points');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('infractions');
        Schema::drop('infractions_type');
    }
}
