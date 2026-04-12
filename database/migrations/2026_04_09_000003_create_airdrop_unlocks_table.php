<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAirdropUnlocksTable extends Migration
{
    public function up()
    {
        Schema::create('airdrop_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('campaign_id');

            // USDT fee paid to unlock
            $table->decimal('usdt_paid', 18, 6)->default(0);

            // On-chain tx hash of the unlock() call (optional — set after on-chain confirmation)
            $table->string('tx_hash', 66)->nullable();

            // Total OBX released to the user's wallet
            $table->string('obx_released', 36)->default('0');

            $table->timestamp('unlocked_at')->nullable();

            // pending = user requested unlock; confirmed = on-chain confirmed
            $table->enum('status', ['pending', 'confirmed'])->default('pending');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('campaign_id')->references('id')->on('airdrop_campaigns')->onDelete('cascade');

            // One unlock record per user per campaign
            $table->unique(['user_id', 'campaign_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('airdrop_unlocks');
    }
}
