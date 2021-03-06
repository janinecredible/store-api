<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');

            $table->string('name')->unique();
            $table->string('code')->nullable();

            $table->text('description')->nullable();
            $table->string('image_url')->nullable();

            $table->string('status')->default('active');

            $table->integer('product_category_id')->unsigned()->nullable();
            $table->foreign('product_category_id')
                ->references('id')
                ->on('product_categories')
                ->onDelete('set null');

            $table->integer('product_condition_id')->unsigned()->nullable();
            $table->foreign('product_condition_id')
                ->references('id')
                ->on('product_conditions')
                ->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
