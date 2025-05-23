<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCharacteristicsTable extends Migration
{
    public function up()
    {
        Schema::create('characteristics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('characteristics');
    }
}
