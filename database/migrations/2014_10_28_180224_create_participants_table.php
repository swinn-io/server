<?php

use Cmgmyr\Messenger\Models\Models;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParticipantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(Models::table('participants'), function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('thread_id');
            $table->uuid('user_id');
            $table->timestamp('last_read')->nullable();
            $table->timestamps();

            $table->primary(['id']);
            $table->unique(['thread_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(Models::table('participants'));
    }
}
