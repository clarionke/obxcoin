<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HardenMultisigAndAddSignatoryChangeRequests extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('wallet_co_users', 'can_approve')) {
            Schema::table('wallet_co_users', function (Blueprint $table) {
                $table->tinyInteger('can_approve')->default(0)->after('status');
            });
        }

        try {
            Schema::table('wallet_co_users', function (Blueprint $table) {
                $table->index(['wallet_id', 'can_approve']);
            });
        } catch (\Exception $e) {
            // Index may already exist on environments where schema was patched manually.
        }

        if (!Schema::hasTable('co_wallet_signatory_change_requests')) {
            Schema::create('co_wallet_signatory_change_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('wallet_id');
                $table->bigInteger('requested_by_admin_id')->nullable();
                $table->bigInteger('requested_by_user_id')->nullable();
                $table->bigInteger('target_user_id');
                $table->bigInteger('target_wallet_co_user_id');
                $table->tinyInteger('requested_can_approve')->default(0);
                $table->tinyInteger('status')->default(STATUS_PENDING);
                $table->timestamps();

                $table->index(['wallet_id', 'status']);
                $table->index(['target_wallet_co_user_id']);
            });
        }

        if (!Schema::hasTable('co_wallet_signatory_change_approvals')) {
            Schema::create('co_wallet_signatory_change_approvals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('request_id');
                $table->bigInteger('wallet_id');
                $table->bigInteger('user_id');
                $table->timestamps();

                $table->index(['request_id', 'user_id']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('co_wallet_signatory_change_approvals')) {
            Schema::dropIfExists('co_wallet_signatory_change_approvals');
        }

        if (Schema::hasTable('co_wallet_signatory_change_requests')) {
            Schema::dropIfExists('co_wallet_signatory_change_requests');
        }

        if (Schema::hasColumn('wallet_co_users', 'can_approve')) {
            try {
                Schema::table('wallet_co_users', function (Blueprint $table) {
                    $table->dropIndex(['wallet_id', 'can_approve']);
                });
            } catch (\Exception $e) {
                // Ignore when index does not exist.
            }

            Schema::table('wallet_co_users', function (Blueprint $table) {
                $table->dropColumn('can_approve');
            });
        }
    }
}
