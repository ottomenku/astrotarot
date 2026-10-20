<?php

namespace Tests\Feature;

use App\Models\CardPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminCardPacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_delete_a_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.packs.index'))
            ->assertOk()
            ->assertSee(__('admin.packs.title'), false)
            ->assertSee('Égi Orákulum', false)
            ->assertSee('Anime orákulum', false)
            ->assertSee('Anime tarot', false)
            ->assertSee('Cosplay orákulum', false)
            ->assertSee('Cosplay tarot', false)
            ->assertSee('Tacskó orákulum', false)
            ->assertSee('Tacskó tarot', false);

        $this->actingAs($admin)
            ->get(route('admin.packs.create', ['type' => 'oracle']))
            ->assertOk()
            ->assertSee(__('admin.packs.name_hu'), false);

        $this->actingAs($admin)
            ->post(route('admin.packs.store'), [
                'name_hu' => 'Teszt orákulum',
                'name_en' => 'Test oracle',
                'slug' => 'teszt-orakulum',
                'description_hu' => 'Rövid magyar leírás.',
                'description_en' => 'Short English description.',
                'type' => CardPack::TYPE_ORACLE,
                'card_count' => 12,
                'style' => 'modern',
                'owner' => 'AstroTarot',
                'sort_order' => 40,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $pack = CardPack::query()->where('slug', 'teszt-orakulum')->firstOrFail();
        $this->assertSame('Teszt orákulum', $pack->name_hu);
        $this->assertSame(CardPack::TYPE_ORACLE, $pack->type);
        $this->assertSame(12, $pack->card_count);
        $this->assertFalse($pack->is_default);

        $this->actingAs($admin)
            ->put(route('admin.packs.update', $pack), [
                'name_hu' => 'Teszt orákulum plusz',
                'name_en' => 'Test oracle plus',
                'slug' => 'teszt-orakulum',
                'description_hu' => 'Frissítve',
                'description_en' => 'Updated',
                'type' => CardPack::TYPE_ORACLE,
                'card_count' => 14,
                'style' => 'classic',
                'owner' => 'AstroTarot',
                'sort_order' => 40,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.packs.edit', $pack));

        $this->assertSame('Teszt orákulum plusz', $pack->fresh()->name_hu);
        $this->assertSame(14, $pack->fresh()->card_count);

        $this->actingAs($admin)
            ->delete(route('admin.packs.destroy', $pack))
            ->assertRedirect(route('admin.packs.index'));

        $this->assertDatabaseMissing('card_packs', ['id' => $pack->id]);
        File::deleteDirectory(public_path('card-pack/teszt-orakulum'));
    }

    public function test_existing_packs_are_imported_and_cards_can_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = CardPack::query()->where('slug', 'tarot')->firstOrFail();
        $card = $pack->cards()->where('figure_id', '00-the-fool')->firstOrFail();
        $entry = \App\Models\MeaningEntry::query()
            ->where('meaning_group_id', $pack->meaning_group_id)
            ->where('figure_id', '00-the-fool')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.packs.edit', $pack))
            ->assertOk()
            ->assertSee($card->name_hu, false)
            ->assertSee('data-card-preview', false);

        $this->actingAs($admin)
            ->put(route('admin.packs.update', $pack), [
                'name_hu' => $pack->name_hu,
                'name_en' => $pack->name_en,
                'slug' => $pack->slug,
                'description_hu' => (string) $pack->description_hu,
                'description_en' => (string) $pack->description_en,
                'type' => $pack->type,
                'card_count' => $pack->card_count,
                'style' => (string) $pack->style,
                'owner' => (string) $pack->owner,
                'sort_order' => $pack->sort_order,
                'is_active' => '1',
                'is_default' => $pack->is_default ? '1' : '0',
                'meaning_group_id' => $pack->meaning_group_id,
                'cards' => [
                    $card->id => [
                        'meaning_entry_id' => $entry->id,
                        'name_hu' => $entry->name_hu,
                        'name_en' => $entry->name_en,
                        'keyword_hu' => $entry->meaning_hu,
                        'keyword_en' => $entry->meaning_en,
                        'description_hu' => 'Kézzel átírt bolond.',
                        'description_en' => $entry->description_en,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.packs.edit', $pack));

        $card->refresh();
        $this->assertSame($entry->id, $card->meaning_entry_id);
        $this->assertSame($entry->name_hu, $card->name_hu);
        $this->assertSame('Kézzel átírt bolond.', $card->description_hu);
        $this->assertGreaterThan(20, $pack->cards()->count());
    }

    public function test_card_image_can_be_replaced(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = CardPack::factory()->create([
            'slug' => 'teszt-lapkep',
            'type' => CardPack::TYPE_TAROT,
        ]);
        $dir = public_path('card-pack/teszt-lapkep/figures');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/sun.png', 'old');
        $card = $pack->cards()->create([
            'figure_id' => 'sun',
            'name_hu' => 'Nap',
            'name_en' => 'Sun',
            'image_path' => 'card-pack/teszt-lapkep/figures/sun.png',
            'sort_order' => 0,
        ]);

        try {
            $this->actingAs($admin)
                ->put(route('admin.packs.update', $pack), [
                    'name_hu' => $pack->name_hu,
                    'name_en' => $pack->name_en,
                    'slug' => $pack->slug,
                    'description_hu' => (string) $pack->description_hu,
                    'description_en' => (string) $pack->description_en,
                    'type' => $pack->type,
                    'card_count' => $pack->card_count,
                    'style' => (string) $pack->style,
                    'owner' => (string) $pack->owner,
                    'sort_order' => $pack->sort_order,
                    'is_active' => '1',
                    'cards' => [
                        $card->id => [
                            'name_hu' => 'Nap kézzel',
                            'name_en' => 'Sun',
                            'image' => UploadedFile::fake()->image('sun.png', 40, 60),
                        ],
                    ],
                ])
                ->assertRedirect(route('admin.packs.edit', $pack));

            $card->refresh();
            $this->assertSame('Nap kézzel', $card->name_hu);
            $this->assertSame('card-pack/teszt-lapkep/figures/sun.png', $card->image_path);
            $this->assertGreaterThan(100, filesize(public_path($card->image_path)));
        } finally {
            File::deleteDirectory(public_path('card-pack/teszt-lapkep'));
        }
    }

    public function test_making_a_pack_default_clears_the_previous_default_of_the_same_type(): void
    {
        $admin = User::factory()->admin()->create();
        $tarot = CardPack::query()->where('slug', 'tarot')->firstOrFail();
        $other = CardPack::factory()->create([
            'type' => CardPack::TYPE_TAROT,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.packs.default', $other))
            ->assertRedirect(route('admin.packs.index'));

        $this->assertTrue($other->fresh()->is_default);
        $this->assertFalse($tarot->fresh()->is_default);
    }

    public function test_non_admin_cannot_manage_packs(): void
    {
        $user = User::factory()->create();
        $pack = CardPack::query()->where('slug', 'tarot')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.packs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.packs.store'), [
                'name_hu' => 'Tiltott',
                'name_en' => 'Forbidden',
                'type' => CardPack::TYPE_TAROT,
                'sort_order' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('admin.packs.destroy', $pack))
            ->assertForbidden();
    }
}
