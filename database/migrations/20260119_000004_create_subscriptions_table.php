<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'name' => '20260119_000004_create_subscriptions_table',
    'up' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->integer('trial_days')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('renewal_mode', 20)->default('manual');
            $table->string('payment_method', 50)->nullable();
            $table->decimal('price_paid', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->integer('billing_period_months')->nullable();
            $table->date('next_renewal_at')->nullable();
            $table->timestamps();

            $table->index('shop_id');
            $table->index('package_id');
            $table->index('expires_at');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->foreign('package_id')->references('id')->on('packages')->onDelete('restrict');
        });
    },
    'down' => function (Capsule $capsule) {
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->drop('subscriptions');
    },
];
