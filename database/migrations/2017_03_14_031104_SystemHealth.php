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

        Schema::create('syshealth', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('group_id');
            $table->timestamp('inserted_on');
            $table->boolean('alive');
            $table->boolean('firewall');
            $table->string('storageKB');

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
    }
}
