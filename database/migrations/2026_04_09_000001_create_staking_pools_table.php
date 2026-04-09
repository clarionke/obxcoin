<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStakingPoolsTable extends Migration
{
    public function up()
    {
        Schema::create('staking_pools', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedInteger('pool_id_onchain')->nullable();    // index in OBXStaking.pools[]
            $table->decimal('min_amount', 30, 8)->default(0);         // minimum OBX to stake
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('apy_bps');                        // APY basis points (500=5%)
            $table->unsignedSmallInteger('burn_on_stake_bps')->default(100);   // 1%
            $table->unsignedSmallInteger('burn_on_unstake_bps')->default(200); // 2%
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);                 // 1=active, 0=inactive
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('staking_pools');
    }
}
