<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_owners', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->text('address');
            $table->timestamps();
        });

        Schema::create('shop_owner_phones', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('shop_owner_id');
            $table->string('phone', 30);
            $table->timestamps();

            $table->foreign('shop_owner_id')
                ->references('id')
                ->on('shop_owners')
                ->onDelete('cascade');
            $table->unique(['shop_owner_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_owner_phones');
        Schema::dropIfExists('shop_owners');
    }
};
