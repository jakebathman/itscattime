<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTwitchUsersTable extends Migration
{
    public function up()
    {
        Schema::create('twitch_users', function (Blueprint $table) {
            $table->id();

            $table->string('twitch_user_id');
            $table->string('login');
            $table->string('display_name');
            $table->string('refresh_token')->nullable();
            $table->string('access_token')->nullable();
            $table->string('token_type')->nullable();
            $table->json('scopes')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('twitch_users');
    }
}
