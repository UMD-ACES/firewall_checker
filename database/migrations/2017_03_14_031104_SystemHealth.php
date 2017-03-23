<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SystemHealth extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
        });

        Schema::create('hosts', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('group_id');
            $table->ipAddress('ip');
        });

        Schema::create('syshealth', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id');
            $table->integer('host_id');
            $table->timestamps();
            $table->boolean('firewall');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
