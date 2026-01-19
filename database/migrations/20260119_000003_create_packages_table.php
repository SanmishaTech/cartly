<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000003_create_packages_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('packages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->decimal('cost_1_month', 12, 2)->nullable();
            $table->decimal('cost_3_month', 12, 2)->nullable();
            $table->decimal('cost_6_month', 12, 2)->nullable();
            $table->decimal('cost_12_month', 12, 2)->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('packages');
    },
];
