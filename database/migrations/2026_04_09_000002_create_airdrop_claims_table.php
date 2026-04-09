<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAirdropClaimsTable extends Migration
{
    public function up()
    {
        Schema::create('airdrop_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('campaign_id');

            // UTC date of the claim (YYYY-MM-DD) — one row per user per day per campaign
            $table->date('claim_date');

            // OBX amount credited (string for bcmath precision)
            $table->string('amount_obx', 36)->default('0');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('campaign_id')->references('id')->on('airdrop_campaigns')->onDelete('cascade');

            // Prevent double-claiming the same UTC day in the same campaign
            $table->unique(['user_id', 'campaign_id', 'claim_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('airdrop_claims');
    }
}
