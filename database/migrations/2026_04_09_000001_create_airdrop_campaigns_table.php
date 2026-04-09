<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAirdropCampaignsTable extends Migration
{
    public function up()
    {
        Schema::create('airdrop_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->dateTime('start_date');
            $table->dateTime('end_date');

            // OBX awarded per user per day (stored as string for bcmath precision)
            $table->string('daily_claim_amount', 36)->default('0');

            // USDT unlock fee — NULL until admin reveals it after campaign ends
            $table->decimal('unlock_fee_usdt', 18, 6)->nullable();
            $table->boolean('fee_revealed')->default(false);

            // On-chain reference (multichain support)
            $table->string('contract_address', 42)->nullable();
            $table->unsignedInteger('chain_id')->nullable();

            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('airdrop_campaigns');
    }
}
