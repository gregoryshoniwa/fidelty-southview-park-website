<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 20)->index();
            $t->string('purpose', 30)->default('login');
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('stands', function (Blueprint $t) {
            $t->id();
            $t->string('stand_number', 20)->unique();
            $t->string('phase', 20)->nullable();
            $t->string('block', 20)->nullable();
            $t->string('street')->nullable();
            $t->string('source', 30)->default('fidelity_import');
            $t->timestamps();
        });

        Schema::create('residents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('stand_id')->nullable()->constrained()->nullOnDelete();
            $t->string('national_id_hash', 64)->nullable()->index();
            $t->string('national_id_last4', 4)->nullable();
            $t->string('verification_status', 20)->default('unverified');
            $t->timestamp('verified_at')->nullable();
            $t->string('fidelity_reference')->nullable();
            $t->string('phone_on_file_masked', 20)->nullable();
            $t->timestamp('consent_fidelity_at')->nullable();
            $t->timestamp('consent_docs_at')->nullable();
            $t->timestamp('consent_marketing_at')->nullable();
            $t->timestamp('consent_assistant_voice_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 40);
            $t->string('text_version', 20);
            $t->timestamp('given_at');
            $t->timestamp('withdrawn_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('partners', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('type', 20);
            $t->string('logo_path')->nullable();
            $t->string('website')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_phone', 20)->nullable();
            $t->json('modules')->nullable();
            $t->json('workflow_steps')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('partner_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20)->default('agent');
            $t->timestamps();
            $t->unique(['partner_id', 'user_id']);
        });

        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('summary', 300);
            $t->longText('body')->nullable();
            $t->string('icon', 40)->default('circle');
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('phase')->default(1);
            $t->boolean('enabled')->default(true);
            $t->boolean('requires_verification')->default(true);
            $t->string('fee_type', 20)->default('none');
            $t->decimal('fee_amount', 14, 2)->default(0);
            $t->char('fee_currency', 3)->default('USD');
            $t->decimal('commission_percent', 6, 3)->default(0);
            $t->json('form_schema')->nullable();
            $t->json('steps')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 30)->default('open')->index();
            $t->unsignedTinyInteger('step')->default(1);
            $t->json('data')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('request_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $t->string('actor_type', 20);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('type', 30);
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kind', 40);
            $t->string('original_name');
            $t->string('storage_path');
            $t->string('mime', 100);
            $t->unsignedInteger('size');
            $t->string('sha256', 64);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('threads', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('subject');
            $t->string('category', 30)->default('general');
            $t->string('status', 20)->default('open')->index();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('first_response_at')->nullable();
            $t->timestamps();
            $t->index(['resident_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained()->cascadeOnDelete();
            $t->string('sender_type', 20);
            $t->unsignedBigInteger('sender_id')->nullable();
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->timestamp('read_by_resident_at')->nullable();
            $t->timestamp('read_by_staff_at')->nullable();
            $t->timestamps();
        });

        Schema::create('sponsorships', function (Blueprint $t) {
            $t->id();
            $t->string('advertiser');
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->string('slot', 40)->index();
            $t->string('headline')->nullable();
            $t->string('body', 300)->nullable();
            $t->string('creative_path')->nullable();
            $t->string('click_url')->nullable();
            $t->string('cta_label', 40)->nullable();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->decimal('price', 14, 2)->default(0);
            $t->char('currency', 3)->default('USD');
            $t->string('status', 20)->default('active');
            $t->unsignedInteger('impressions')->default(0);
            $t->unsignedInteger('clicks')->default(0);
            $t->timestamps();
        });

        Schema::create('notices', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('excerpt', 300)->nullable();
            $t->longText('body');
            $t->string('category', 20)->index();
            $t->string('signed_by_role', 40)->nullable();
            $t->boolean('pinned')->default(false);
            $t->foreignId('sponsorship_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable()->index();
            $t->boolean('send_sms')->default(false);
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->string('audience', 30)->default('open_requests');
            $t->unsignedInteger('recipient_count')->default(0);
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('polls', function (Blueprint $t) {
            $t->id();
            $t->string('question');
            $t->text('description')->nullable();
            $t->json('options');
            $t->timestamp('opens_at');
            $t->timestamp('closes_at');
            $t->timestamp('results_published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stand_id')->constrained()->cascadeOnDelete();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('option_index');
            $t->timestamp('cast_at')->useCurrent();
            $t->unique(['poll_id', 'stand_id']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->string('purpose', 30)->default('bill');
            $t->string('biller_code', 30);
            $t->string('biller_reference', 60);
            $t->decimal('amount', 14, 2);
            $t->decimal('platform_fee', 14, 2)->default(0);
            $t->decimal('commission', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->string('gateway', 20)->default('tncb');
            $t->string('gateway_reference', 80)->nullable()->unique();
            $t->string('status', 20)->default('initiated')->index();
            $t->json('gateway_payload')->nullable();
            $t->string('token_or_voucher')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('receipt_path')->nullable();
            $t->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->date('entry_date')->index();
            $t->string('type', 20);
            $t->string('description');
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sponsorship_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->string('source', 20);
            $t->string('receipt_path')->nullable();
            $t->string('status', 20)->default('posted');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('reverses_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $t->string('hash_prev', 64)->nullable();
            $t->string('hash_self', 64);
            $t->timestamps();
        });

        Schema::create('settlements', function (Blueprint $t) {
            $t->id();
            $t->string('batch_reference')->unique();
            $t->date('period');
            $t->decimal('gross', 14, 2);
            $t->decimal('fees', 14, 2);
            $t->decimal('net', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->unsignedInteger('matched_count')->default(0);
            $t->unsignedInteger('unmatched_count')->default(0);
            $t->json('raw')->nullable();
            $t->timestamps();
        });

        Schema::create('community_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 20)->index();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('tagline', 160)->nullable();
            $t->text('description')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('cover_path')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('address')->nullable();
            $t->json('hours')->nullable();
            $t->boolean('verified')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('page_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_page_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('image_path')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('reported_count')->default(0);
            $t->timestamp('hidden_at')->nullable();
            $t->string('hidden_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('page_followers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_page_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['community_page_id', 'user_id']);
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('community_page_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->unsignedInteger('stock')->default(0);
            $t->string('image_path')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('community_page_id')->constrained()->cascadeOnDelete();
            $t->json('items');
            $t->decimal('total', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });

        Schema::create('school_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('learner_ref', 40);
            $t->string('learner_name');
            $t->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('amount', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->date('due_on');
            $t->string('status', 20)->default('unpaid');
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('security_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $t->string('plan');
            $t->decimal('price', 14, 2);
            $t->char('currency', 3)->default('USD');
            $t->string('status', 20)->default('active');
            $t->date('next_billing_on')->nullable();
            $t->timestamps();
        });

        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $t->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 30);
            $t->string('location')->nullable();
            $t->text('description');
            $t->string('status', 20)->default('open');
            $t->timestamps();
        });

        Schema::create('cms_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->string('meta_description', 300)->nullable();
            $t->longText('body');
            $t->boolean('published')->default(true);
            $t->timestamps();
        });

        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('question');
            $t->text('answer');
            $t->string('topic', 40)->default('general');
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('published')->default(true);
            $t->timestamps();
        });

        Schema::create('minutes', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->date('meeting_date');
            $t->longText('body');
            $t->string('file_path')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('committee_members', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('role');
            $t->string('area')->nullable();
            $t->string('photo_path')->nullable();
            $t->text('bio')->nullable();
            $t->boolean('public')->default(false);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('knowledge_chunks', function (Blueprint $t) {
            $t->id();
            $t->string('source_type', 40);
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('title');
            $t->text('content');
            $t->string('url')->nullable();
            $t->timestamps();
            $t->fullText(['title', 'content']);
        });

        Schema::create('assistant_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('session_id', 64)->index();
            $t->string('mode', 10)->default('text');
            $t->json('transcript')->nullable();
            $t->foreignId('escalated_thread_id')->nullable()->constrained('threads')->nullOnDelete();
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type', 20)->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('action', 60)->index();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('in_app_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('body', 500)->nullable();
            $t->string('link')->nullable();
            $t->string('kind', 30)->default('info');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('sms_logs', function (Blueprint $t) {
            $t->id();
            $t->string('to', 20);
            $t->string('template', 40)->nullable();
            $t->text('body');
            $t->string('status', 20)->default('queued');
            $t->string('provider_reference')->nullable();
            $t->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('endpoint');
            $t->string('p256dh')->nullable();
            $t->string('auth')->nullable();
            $t->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 20)->unique();
            $t->json('categories')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'subscribers', 'push_subscriptions', 'sms_logs', 'in_app_notifications', 'audit_logs', 'assistant_conversations',
            'knowledge_chunks', 'settings', 'committee_members', 'minutes', 'faqs', 'cms_pages', 'incidents',
            'security_subscriptions', 'school_invoices', 'orders', 'products', 'page_followers', 'page_posts',
            'community_pages', 'settlements', 'ledger_entries', 'payments', 'votes', 'polls', 'broadcasts',
            'notices', 'sponsorships', 'messages', 'threads', 'documents', 'request_events', 'service_requests',
            'services', 'partner_user', 'partners', 'consents', 'residents', 'stands', 'otp_codes',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
