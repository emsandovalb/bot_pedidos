<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->string('provider_app_id')->nullable()->after('provider_version');
            $table->text('provider_app_secret')->nullable()->after('provider_app_id');
            $table->text('provider_access_token')->nullable()->after('provider_app_secret');
            $table->text('provider_verify_token')->nullable()->after('provider_access_token');
            $table->text('provider_webhook_secret')->nullable()->after('provider_verify_token');
            $table->string('provider_phone_number_id')->nullable()->after('provider_webhook_secret');
            $table->string('provider_business_account_id')->nullable()->after('provider_phone_number_id');
            $table->string('provider_display_phone')->nullable()->after('provider_business_account_id');
            $table->string('provider_api_version')->nullable()->after('provider_display_phone');
            $table->string('provider_business_name')->nullable()->after('provider_api_version');
            $table->string('provider_business_timezone')->nullable()->after('provider_business_name');
            $table->string('provider_business_country')->nullable()->after('provider_business_timezone');
            $table->string('provider_status')->nullable()->after('provider_business_country');
            $table->string('provider_configuration_status')->nullable()->after('provider_status');
            $table->timestamp('provider_last_validation_at')->nullable()->after('provider_configuration_status');
            $table->text('provider_last_validation_error')->nullable()->after('provider_last_validation_at');
            $table->json('provider_metadata_json')->nullable()->after('provider_last_validation_error');
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropColumn([
                'provider_app_id',
                'provider_app_secret',
                'provider_access_token',
                'provider_verify_token',
                'provider_webhook_secret',
                'provider_phone_number_id',
                'provider_business_account_id',
                'provider_display_phone',
                'provider_api_version',
                'provider_business_name',
                'provider_business_timezone',
                'provider_business_country',
                'provider_status',
                'provider_configuration_status',
                'provider_last_validation_at',
                'provider_last_validation_error',
                'provider_metadata_json',
            ]);
        });
    }
};
