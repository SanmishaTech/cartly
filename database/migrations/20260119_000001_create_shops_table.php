<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000001_create_shops_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('shops', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('slug', 120)->unique();
            $table->string('shop_name', 255);
            $table->text('shop_description')->nullable();
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('India');

            $table->string('logo_path', 255)->nullable();
            $table->string('favicon_path', 255)->nullable();

            $table->string('hero_type', 50)->default('banner');
            $table->json('hero_settings')->nullable();

            $table->string('theme', 50)->default('default');
            $table->json('theme_config')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('status');
            $table->index('created_at');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('shops');
    },
];
