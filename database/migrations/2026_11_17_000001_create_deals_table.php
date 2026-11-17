<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Общая воронка сделок: тендеры (КП победителю) и заявки с сайта.
 *
 * Статус, напоминание и заметка переезжают из offers в deals; в offers остаётся
 * только факт отправки КП (канал, когда, кто) со ссылкой на сделку.
 */
return new class extends Migration
{
    /** Статус КП → статус сделки (решение владельца 17.11). */
    private const STATUS_MAP = [
        'sent' => 'offer_sent',
        'replied' => 'negotiation',
        'deal' => 'contract',
        'refused' => 'refused',
        'dismissed' => 'dismissed',
    ];

    /** До какого этапа воронки дошла сделка с этим статусом КП. */
    private const REACHED_MAP = [
        'sent' => 'offer_sent',
        'replied' => 'negotiation',
        'deal' => 'contract',
        'refused' => 'offer_sent',
        'dismissed' => 'new',
    ];

    /** Обратно: статус сделки → статус КП. */
    private const BACK_MAP = [
        'new' => 'sent',
        'brief_received' => 'sent',
        'offer_sent' => 'sent',
        'negotiation' => 'replied',
        'contract' => 'deal',
        'in_progress' => 'deal',
        'done_paid' => 'deal',
        'refused' => 'refused',
        'dismissed' => 'dismissed',
    ];

    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 16);
            $table->string('status', 24);
            // Самый дальний этап воронки, до которого дошла сделка. Нужен, чтобы отказ
            // после переговоров считался в воронке на этапе переговоров; из статуса не выводится.
            $table->string('reached_status', 24);
            // Тендерные сделки: контракт и победитель.
            $table->foreignId('contract_id')->nullable()->unique()->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            // Заявки с сайта: номер заявки на сайте, «es12-<номер>».
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('organization', 150)->nullable();
            $table->string('facility', 200)->nullable();
            $table->string('service', 200)->nullable();
            $table->string('origin', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('estimate', 500)->nullable();
            $table->text('source_url')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->unsignedBigInteger('offer_amount_kopecks')->nullable();
            $table->unsignedBigInteger('contract_amount_kopecks')->nullable();
            $table->unsignedBigInteger('paid_kopecks')->nullable();
            $table->date('remind_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index('source');
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->foreignId('deal_id')->nullable()->after('id')->constrained('deals')->cascadeOnDelete();
        });

        DB::transaction(function (): void {
            $offers = DB::table('offers')
                ->join('contracts', 'contracts.id', '=', 'offers.contract_id')
                ->select('offers.*', 'contracts.company_id')
                ->orderBy('offers.id')
                ->get();

            foreach ($offers as $offer) {
                $status = (string) $offer->status;
                $dealId = DB::table('deals')->insertGetId([
                    'source' => 'tender',
                    'status' => self::STATUS_MAP[$status] ?? 'offer_sent',
                    'reached_status' => self::REACHED_MAP[$status] ?? 'offer_sent',
                    'contract_id' => $offer->contract_id,
                    'company_id' => $offer->company_id,
                    'received_at' => $offer->sent_at ?? $offer->created_at,
                    'remind_on' => $offer->remind_on,
                    'note' => $offer->note,
                    'user_id' => $offer->user_id,
                    'created_at' => $offer->created_at,
                    'updated_at' => $offer->updated_at,
                ]);

                DB::table('offers')->where('id', $offer->id)->update(['deal_id' => $dealId]);
            }

            // «Не интересно» без отправки: КП не было, решение хранит сделка.
            DB::table('offers')->whereNull('channel')->delete();
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropColumn(['status', 'remind_on', 'note']);
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->foreignId('deal_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Заявки с сайта и прочие нетендерные сделки в старой схеме хранить негде —
        // откат не должен молча их стереть.
        if (DB::table('deals')->where('source', '!=', 'tender')->exists()) {
            throw new RuntimeException('Откат удалил бы нетендерные сделки (заявки с сайта). Сначала выгрузите их.');
        }

        Schema::table('offers', function (Blueprint $table): void {
            $table->string('status', 16)->nullable();
            $table->date('remind_on')->nullable();
            $table->text('note')->nullable();
        });

        DB::transaction(function (): void {
            $deals = DB::table('deals')->whereNotNull('contract_id')->orderBy('id')->get();

            foreach ($deals as $deal) {
                $values = [
                    'status' => self::BACK_MAP[(string) $deal->status] ?? 'sent',
                    'remind_on' => $deal->remind_on,
                    'note' => $deal->note,
                ];

                $updated = DB::table('offers')->where('deal_id', $deal->id)->update($values);

                if ($updated === 0) {
                    DB::table('offers')->insert($values + [
                        'deal_id' => $deal->id,
                        'contract_id' => $deal->contract_id,
                        'channel' => null,
                        'sent_at' => null,
                        'user_id' => $deal->user_id,
                        'created_at' => $deal->created_at ?? Carbon::now(),
                        'updated_at' => $deal->updated_at ?? Carbon::now(),
                    ]);
                }
            }
        });

        Schema::table('offers', function (Blueprint $table): void {
            $table->string('status', 16)->nullable(false)->change();
            $table->index(['status', 'updated_at']);
            $table->dropConstrainedForeignId('deal_id');
        });

        Schema::dropIfExists('deals');
    }
};
