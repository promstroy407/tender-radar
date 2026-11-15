<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MessengerLinkBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessengerLinkBuilderTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function phones(): array
    {
        return [
            'с плюсом и пробелами' => ['+7 937 112-48-97', '79371124897'],
            'с восьмёркой' => ['8 (937) 112-48-97', '79371124897'],
            'десять цифр' => ['9371124897', '79371124897'],
            'короткий' => ['112-48-97', null],
            'чужая страна' => ['+44 20 7946 0958', null],
            'пусто' => [null, null],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_is_normalized(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, (new MessengerLinkBuilder)->normalizePhone($input));
    }

    public function test_whatsapp_link_carries_text(): void
    {
        $url = (new MessengerLinkBuilder)->whatsapp('8 937 112 48 97', 'Здравствуйте! КП & смета');

        $this->assertSame('https://wa.me/79371124897?text='.rawurlencode('Здравствуйте! КП & смета'), $url);
    }

    public function test_no_links_without_phone(): void
    {
        $links = new MessengerLinkBuilder;

        $this->assertNull($links->whatsapp(null, 'текст'));
        $this->assertNull($links->telegram('123'));
        $this->assertSame('https://t.me/+79371124897', $links->telegram('+79371124897'));
    }
}
