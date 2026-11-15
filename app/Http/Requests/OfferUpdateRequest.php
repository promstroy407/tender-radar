<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\OfferChange;
use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OfferUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer && ($this->user()?->can('update', $offer) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OfferStatus::class)],
            'remind_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before:2100-01-01'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toChange(): OfferChange
    {
        $remindOn = $this->validated('remind_on');
        $note = $this->validated('note');

        return new OfferChange(
            status: $this->enum('status', OfferStatus::class) ?? OfferStatus::Sent,
            remindOn: is_string($remindOn) ? Carbon::createFromFormat('Y-m-d', $remindOn)?->startOfDay() : null,
            note: is_string($note) && trim($note) !== '' ? trim($note) : null,
        );
    }
}
