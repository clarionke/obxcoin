<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGaslessSponsorshipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gasless_sponsorships', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('wallet_address', 42);
            $table->string('action', 32);
            $table->unsignedInteger('chain_id')->default(56);
            $table->decimal('gas_amount_native', 24, 12)->default(0);
            $table->unsignedInteger('estimated_gas_limit')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('tx_hash', 66)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['wallet_address', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gasless_sponsorships');
    }
}
