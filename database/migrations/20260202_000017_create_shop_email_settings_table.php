<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260202_000017_create_shop_email_settings_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('shop_email_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_id');
            $table->string('email_mode', 32)->default('global');
            $table->string('from_name', 191)->nullable();
            $table->string('from_email', 191)->nullable();
            $table->string('reply_to_email', 191)->nullable();
            $table->string('reply_to_name', 191)->nullable();
            $table->string('domain', 191)->nullable();
            $table->boolean('domain_verified')->default(false);
            $table->string('provider', 32)->default('brevo');
            $table->unsignedInteger('daily_email_count')->default(0);
            $table->unsignedInteger('monthly_email_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('shop_id', 'shop_email_settings_shop_id_unique');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('shop_email_settings');
    },
];
