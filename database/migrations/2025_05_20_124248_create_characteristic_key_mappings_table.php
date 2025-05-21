<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCharacteristicKeyMappingsTable extends Migration
{
    public function up()
    {
        Schema::create('characteristic_key_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('site');
            $table->string('remote_key');
            $table->unsignedBigInteger('characteristic_id');
            $table->string('file_name');
            $table->timestamps();

            $table->foreign('characteristic_id')
                ->references('id')->on('characteristics')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('characteristic_key_mappings');
    }
}
