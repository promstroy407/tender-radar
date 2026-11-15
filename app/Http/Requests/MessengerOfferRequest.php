<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OfferChannel;
use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessengerOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Offer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(OfferChannel::class)->only([OfferChannel::WhatsApp, OfferChannel::Telegram])],
        ];
    }

    public function channel(): OfferChannel
    {
        return $this->enum('channel', OfferChannel::class) ?? OfferChannel::WhatsApp;
    }
}
