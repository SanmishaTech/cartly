<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000007_create_password_resets_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('password_resets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('password_resets');
    },
];
