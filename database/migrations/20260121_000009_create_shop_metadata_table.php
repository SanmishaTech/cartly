<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260121_000009_create_shop_metadata_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('shop_metadata', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_id');
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

            $table->json('social_media_links')->nullable();
            $table->json('home_sections')->nullable();
            $table->json('home_content')->nullable();
            $table->json('footer_content')->nullable();
            $table->json('third_party')->nullable();
            $table->json('oauth_config')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('shop_id', 'unique_shop');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('shop_metadata');
    },
];
