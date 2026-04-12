<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStakingPositionsTable extends Migration
{
    public function up()
    {
        Schema::create('staking_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pool_id');                     // FK → staking_pools.id
            $table->string('wallet_address', 42);                      // user's BSC address
            $table->unsignedInteger('contract_stake_idx')->nullable(); // index in stakes[address][] on-chain
            $table->decimal('gross_amount', 30, 8);                    // OBX sent to contract
            $table->decimal('burned_on_stake', 30, 8)->default(0);    // burned immediately
            $table->decimal('net_amount', 30, 8);                     // held by contract
            $table->decimal('reward_earned', 30, 8)->default(0);      // at time of unstake
            $table->decimal('burned_on_unstake', 30, 8)->default(0);
            $table->decimal('returned_amount', 30, 8)->default(0);     // principal returned
            $table->string('status', 20)->default('pending');          // pending|active|unstaked|failed
            $table->string('tx_hash_stake', 66)->nullable();
            $table->string('tx_hash_unstake', 66)->nullable();
            $table->timestamp('staked_at')->nullable();
            $table->timestamp('lock_until')->nullable();
            $table->timestamp('unstaked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('pool_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staking_positions');
    }
}
