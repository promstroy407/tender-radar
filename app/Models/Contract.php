<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractKind;
use App\Enums\ProcurementLaw;
use App\Enums\WorkSection;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Заключённый контракт из реестра ЕИС с известным победителем.
 *
 * @property int $id
 * @property string $registry_number
 * @property string|null $notification_number
 * @property Carbon|null $documents_checked_at
 * @property ProcurementLaw $law
 * @property ContractKind $kind
 * @property string $region_code
 * @property string $subject
 * @property string $customer_name
 * @property string|null $customer_inn
 * @property int $amount_kopecks
 * @property Carbon $signed_at
 * @property Carbon|null $ends_at
 * @property Collection<int, WorkSection> $sections
 * @property bool $requires_smp_subcontract
 * @property int $company_id
 * @property-read Company $company
 * @property-read Region $region
 * @property-read Offer|null $offer
 * @property-read Deal|null $deal
 * @property-read Estimate|null $estimate
 * @property-read EloquentCollection<int, ContractDocument> $documents
 * @property-read EloquentCollection<int, User> $viewers
 * @property-read bool|null $viewed  есть, когда лента запрошена для пользователя
 */
#[Fillable([
    'registry_number', 'notification_number', 'documents_checked_at', 'law', 'kind', 'region_code', 'subject', 'customer_name', 'customer_inn',
    'amount_kopecks', 'signed_at', 'ends_at', 'sections', 'requires_smp_subcontract', 'company_id',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    /**
     * @return HasOne<Estimate, $this>
     */
    public function estimate(): HasOne
    {
        return $this->hasOne(Estimate::class);
    }

    /**
     * @return HasOne<Offer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    /**
     * Файлы документации закупки (из извещения в ЕИС).
     *
     * @return HasMany<ContractDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ContractDocument::class)->orderBy('name');
    }

    /**
     * Кто из пользователей уже открывал тендер.
     *
     * @return BelongsToMany<User, $this>
     */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contract_views')->withPivot('viewed_at');
    }

    /**
     * Сделка по контракту: появляется, когда КП отправлено или тендер отмечен «Не интересно».
     *
     * @return HasOne<Deal, $this>
     */
    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }

    /**
     * «Горячий» тендер: победитель обязан нанять субподрядчика,
     * и в объёме есть наши разделы работ. Вычисляется, не хранится.
     */
    public function isHot(): bool
    {
        return $this->requires_smp_subcontract && $this->sections->isNotEmpty();
    }

    /**
     * То же правило «горячего» тендера на уровне SQL.
     *
     * @param  Builder<Contract>  $query
     */
    public function scopeHot(Builder $query): void
    {
        $query->where('requires_smp_subcontract', true)
            ->whereJsonLength('sections', '>', 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'law' => ProcurementLaw::class,
            'kind' => ContractKind::class,
            'amount_kopecks' => 'integer',
            'signed_at' => 'date',
            'ends_at' => 'date',
            'documents_checked_at' => 'datetime',
            'sections' => AsEnumCollection::of(WorkSection::class),
            'requires_smp_subcontract' => 'boolean',
        ];
    }
}
