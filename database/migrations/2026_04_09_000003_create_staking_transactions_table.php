<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStakingTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('staking_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('position_id')->nullable();     // FK → staking_positions.id
            $table->string('type', 20);                                // stake_in|unstake_out|burn_stake|burn_unstake|reward
            $table->decimal('amount', 30, 8);
            $table->string('tx_hash', 66)->nullable();
            $table->string('status', 20)->default('confirmed');        // confirmed|pending|failed
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('position_id');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staking_transactions');
    }
}
