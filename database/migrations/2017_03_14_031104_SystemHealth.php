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
            $table->ipAddress('ip');
        });

        Schema::create('honeypots', function (Blueprint $table)
        {
           $table->increments('id');
           $table->integer('group_id');
           $table->ipAddress('ip');
        });

        Schema::create('syshealth', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id');
            $table->timestamp('inserted_on');
            $table->boolean('alive');
            $table->text('alive_error');
            $table->boolean('firewall');
            $table->unsignedBigInteger('transferInMB');
            $table->unsignedBigInteger('transferOutMB');
            $table->unsignedBigInteger('storageMB');
            $table->unsignedBigInteger('memoryMB');
            $table->float('cpuLoad');
            $table->text('upTime');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('syshealth');
        Schema::drop('groups');
        Schema::drop('honeypots');
    }
}
