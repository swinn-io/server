<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('name');
            $table->string('provider_name');
            $table->string('provider_id');
            $table->string('email')->nullable();
            $table->json('notify_via');
            $table->string('access_token');
            $table->string('refresh_token')->nullable();
            $table->json('profile');
            $table->rememberToken();
            $table->timestamps();

            $table->primary(['id']);
            $table->unique(['provider_name', 'provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
