<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000002_create_shop_domains_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('shop_domains', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_id');
            $table->string('domain', 255)->unique();
            $table->boolean('is_primary')->default(true);
            $table->boolean('is_temp')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->enum('status', ['pending', 'active'])->default('pending');
            $table->timestamps();

            $table->index('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('shop_domains');
    },
];
