<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260122_000012_create_menu_items_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('menu_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('menu_id');
            $table->string('label', 120);
            $table->enum('type', ['page', 'url'])->default('page');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('url', 255)->nullable();
            $table->unsignedInteger('menu_order')->default(0);
            $table->timestamps();

            $table->index('menu_id');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('menu_items');
    },
];
