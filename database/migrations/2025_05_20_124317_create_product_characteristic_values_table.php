<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductCharacteristicValuesTable extends Migration
{
    public function up()
    {
        Schema::create('product_characteristic_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('characteristic_id')->nullable();
            $table->unsignedBigInteger('characteristic_key_mapping_id')->nullable();
            $table->string('value');
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');
            $table->foreign('characteristic_id')
                ->references('id')->on('characteristics')
                ->onDelete('cascade');
            $table->foreign('characteristic_key_mapping_id', 'fk_pcv_key_mapping')
                ->references('id')->on('characteristic_key_mappings')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_characteristic_values');
    }
}
