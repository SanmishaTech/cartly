<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260122_000010_create_pages_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_id');
            $table->string('slug', 150);
            $table->string('title', 255);
            $table->enum('type', ['standard', 'system'])->default('standard');
            $table->json('content_json')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('show_in_menu')->default(false);
            $table->unsignedInteger('menu_order')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'slug'], 'unique_page_slug');
            $table->index(['shop_id', 'status'], 'pages_shop_status');
            $table->index(['shop_id', 'show_in_menu'], 'pages_shop_menu');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('pages');
    },
];
