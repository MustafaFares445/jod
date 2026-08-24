<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('payment_method');
            $table->string('contact_method')->nullable()->after('status');
            $table->text('notes')->nullable()->after('contact_method');
            $table->text('cancel_reason')->nullable()->after('notes');
            $table->timestamp('contacted_at')->nullable()->after('donated_at');
            $table->timestamp('agreed_at')->nullable()->after('contacted_at');
            $table->timestamp('completed_at')->nullable()->after('agreed_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->string('confirmed_by')->nullable()->after('created_by');

            $table->index(['campaign_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['organization_id', 'status']);
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Before this migration every stored donation was treated as received and already
        // contributed to campaign totals. Preserve that accounting history while new rows
        // start as pending intents through DonationService::createIntent().
        DB::table('donations')->update([
            'status' => 'completed',
            'completed_at' => DB::raw('donated_at'),
        ]);

        Schema::table('posts', function (Blueprint $table): void {
            $table->string('help_status')->nullable()->after('status')->index();
        });

        DB::table('posts')
            ->where('type', 'help_request')
            ->update(['help_status' => 'open']);

        Schema::create('help_offers', function (Blueprint $table): void {
            $table->id();
            $table->string('post_id');
            $table->string('helper_user_id');
            $table->string('post_owner_id');
            $table->string('type');
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('contact_method')->nullable();
            $table->string('phone')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->timestamp('helper_confirmed_at')->nullable();
            $table->timestamp('receiver_confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            $table->foreign('helper_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('post_owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['post_id', 'status']);
            $table->index(['helper_user_id', 'status']);
            $table->index(['post_owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_offers');

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('help_status');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn([
                'status', 'contact_method', 'notes', 'cancel_reason', 'contacted_at',
                'agreed_at', 'completed_at', 'cancelled_at', 'confirmed_by',
            ]);
        });
    }
};
