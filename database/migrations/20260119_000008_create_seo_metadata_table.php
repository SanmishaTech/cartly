<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000008_create_seo_metadata_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('seo_metadata', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->enum('entity_type', ['shop', 'product', 'category', 'page']);
            $table->unsignedBigInteger('entity_id');

            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('seo_keywords', 255)->nullable();

            $table->string('canonical_url', 255)->nullable();

            $table->string('og_title', 255)->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image', 255)->nullable();

            $table->json('schema_json')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['entity_type', 'entity_id'], 'unique_entity');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('seo_metadata');
    },
];
