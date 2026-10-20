<?php

namespace Tests\Feature;

use App\Models\CardPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PackInstallTest extends TestCase
{
    use RefreshDatabase;

    private string $slug = 'teszt-pakli';

    protected function tearDown(): void
    {
        $this->deleteTree(public_path('card-pack/'.$this->slug));
        $this->deleteTree(public_path('card-pack/teszt-fajlbol'));
        parent::tearDown();
    }

    public function test_admin_installs_a_pack_from_json_and_from_filenames(): void
    {
        $admin = User::factory()->admin()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)
            ->get(route('admin.packs.install'))
            ->assertOk()
            ->assertSee('Kártya telepítés', false);

        $this->actingAs($admin)
            ->post(route('admin.packs.install.store'), [
                'slug' => $this->slug,
                'card_count' => 22,
                'name_hu' => 'Teszt pakli',
                'name_en' => 'Test pack',
                'description_hu' => 'Magyar leírás',
                'description_en' => 'English description',
                'type' => 'tarot',
                'archive' => $this->zip([
                    'deck.json' => json_encode([
                        'style' => 'test-style',
                        'figures' => [
                            ['id' => 'sun', 'hu' => 'Nap', 'en' => 'Sun', 'keyword' => ['hu' => 'fény', 'en' => 'light'], 'blurb' => ['hu' => 'A nap süt.', 'en' => 'The sun shines.']],
                            ['id' => 'moon', 'hu' => 'Hold', 'en' => 'Moon'],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'figures/sun.png' => $png,
                    'figures/moon-light.png' => $png,
                    'figures/moon-shadow.png' => $png,
                    'card-back/card-back.png' => $png,
                    '../evil.png' => $png,
                ]),
            ])
            ->assertRedirect();

        $pack = CardPack::query()->where('slug', $this->slug)->firstOrFail();
        $this->assertSame('Teszt pakli', $pack->name_hu);
        $this->assertSame(22, $pack->card_count);
        $this->assertSame('tarot', $pack->type);
        $this->assertSame('test-style', $pack->style);
        $this->assertSame(2, $pack->cards()->count());
        $sun = $pack->cards()->where('figure_id', 'sun')->firstOrFail();
        $this->assertSame('Nap', $sun->name_hu);
        $this->assertSame('fény', $sun->keyword_hu);
        $this->assertSame('A nap süt.', $sun->description_hu);
        $this->assertSame('card-pack/'.$this->slug.'/figures/sun.png', $sun->image_path);
        $this->assertFileExists(public_path('card-pack/'.$this->slug.'/deck.json'));
        $this->assertFileExists(public_path('card-pack/'.$this->slug.'/figures/moon-light.png'));
        $this->assertFileExists(public_path('card-pack/'.$this->slug.'/card-back/card-back.png'));
        $this->assertFileDoesNotExist(public_path('evil.png'));

        $this->actingAs($admin)
            ->post(route('admin.packs.install.store'), [
                'slug' => 'teszt-fajlbol',
                'card_count' => 2,
                'name_hu' => 'Fájlból',
                'name_en' => 'From files',
                'type' => 'oracle',
                'archive' => $this->zip([
                    'nap.png' => $png,
                    'hold-light.png' => $png,
                    'hold-shadow.png' => $png,
                ]),
            ])
            ->assertRedirect();

        $plain = CardPack::query()->where('slug', 'teszt-fajlbol')->firstOrFail();
        $this->assertSame(2, $plain->cards()->count());
        $this->assertNotNull($plain->cards()->where('figure_id', 'nap')->first());
        $this->assertNotNull($plain->cards()->where('figure_id', 'hold')->first());
        $this->assertFileExists(public_path('card-pack/teszt-fajlbol/figures/nap.png'));
    }

    public function test_guest_cannot_install_a_pack(): void
    {
        $this->get(route('admin.packs.install'))->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): UploadedFile
    {
        $locals = '';
        $central = '';
        $offset = 0;
        foreach ($files as $name => $contents) {
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);
            $locals .= "PK\x03\x04"
                .pack('v', 20).pack('v', 0).pack('v', 0).pack('v', 0).pack('v', 0)
                .pack('V', $crc).pack('V', $size).pack('V', $size)
                .pack('v', $nameLength).pack('v', 0)
                .$name.$contents;
            $central .= "PK\x01\x02"
                .pack('v', 20).pack('v', 20).pack('v', 0).pack('v', 0).pack('v', 0).pack('v', 0)
                .pack('V', $crc).pack('V', $size).pack('V', $size)
                .pack('v', $nameLength).pack('v', 0).pack('v', 0).pack('v', 0).pack('v', 0)
                .pack('V', 0).pack('V', $offset)
                .$name;
            $offset += 30 + $nameLength + $size;
        }
        $binary = $locals.$central."PK\x05\x06"
            .pack('v', 0).pack('v', 0)
            .pack('v', count($files)).pack('v', count($files))
            .pack('V', strlen($central)).pack('V', strlen($locals))
            .pack('v', 0);
        $path = storage_path('app/pack-install-test-'.uniqid('', true).'.zip');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'pack.zip', 'application/zip', null, true);
    }

    private function deleteTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
