<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('website_url')->nullable();
            $table->string('industry')->nullable()->index();
            $table->string('niche')->nullable();
            $table->string('target_age_group')->nullable();
            $table->json('audience_interests')->nullable();
        });

        Schema::table('brand_settings', function (Blueprint $table) {
            $table->json('brand_personality')->nullable();
            $table->json('brand_values')->nullable();
            $table->json('brand_keywords')->nullable();
            $table->json('topics_to_avoid')->nullable();
            $table->json('marketing_objectives')->nullable();
            $table->json('content_preferences')->nullable();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('status')->default('not_connected')->change();
        });

        DB::table('social_accounts')
            ->where('status', 'connected')
            ->update(['status' => 'connection_error']);

        $placeholderAccountIds = [];

        DB::table('social_account_tokens')
            ->orderBy('id')
            ->chunkById(100, function ($tokens) use (&$placeholderAccountIds): void {
                foreach ($tokens as $token) {
                    $updates = [];

                    foreach (['access_token', 'refresh_token'] as $column) {
                        $value = $token->{$column};
                        if ($value === null || $value === '') {
                            continue;
                        }

                        if ($column === 'access_token' && $value === 'oauth-token-placeholder') {
                            $placeholderAccountIds[] = $token->social_account_id;
                        }

                        try {
                            Crypt::decryptString($value);
                        } catch (DecryptException) {
                            $updates[$column] = Crypt::encryptString($value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table('social_account_tokens')->where('id', $token->id)->update($updates);
                    }
                }
            });

        if ($placeholderAccountIds !== []) {
            DB::table('social_accounts')
                ->whereIn('id', $placeholderAccountIds)
                ->update(['status' => 'connection_error']);
        }
    }

    public function down(): void
    {
        Schema::table('brand_settings', function (Blueprint $table) {
            $table->dropColumn([
                'brand_personality',
                'brand_values',
                'brand_keywords',
                'topics_to_avoid',
                'marketing_objectives',
                'content_preferences',
            ]);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['industry']);
            $table->dropColumn([
                'website_url',
                'industry',
                'niche',
                'target_age_group',
                'audience_interests',
            ]);
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('status')->default('connected')->change();
        });
    }
};
