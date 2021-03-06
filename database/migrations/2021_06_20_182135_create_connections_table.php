<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {//TODO change to user-genre_category link
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')
                ->constrained('artists')
                ->onDelete('cascade');
            $table->foreignId('genre_id')
                ->constrained('genres')
                ->onDelete('cascade');
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
        Schema::dropIfExists('connections');
    }
}
