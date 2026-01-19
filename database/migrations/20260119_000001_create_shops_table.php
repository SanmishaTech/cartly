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

            $table->string('default_seo_title', 255)->nullable();
            $table->string('default_seo_description', 500)->nullable();
            $table->string('seo_keywords', 500)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('meta_robots', 50)->default('index,follow');

            $table->json('home_seo')->nullable();

            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('social_image_path', 255)->nullable();
            $table->string('twitter_card_type', 50)->default('summary_large_image');

            $table->string('logo_path', 255)->nullable();
            $table->string('favicon_path', 255)->nullable();

            $table->string('hero_type', 50)->default('carousel');
            $table->json('hero_settings')->nullable();

            $table->string('theme', 50)->default('default');
            $table->json('theme_config')->nullable();

            $table->enum('status', ['active', 'inactive', 'suspended', 'draft'])->default('active');

            $table->boolean('sitemap_enabled')->default(true);
            $table->json('structured_data')->nullable();

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
