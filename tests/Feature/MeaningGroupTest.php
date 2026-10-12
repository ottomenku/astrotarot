<?php

namespace Tests\Feature;

use App\Models\CardPack;
use App\Models\MeaningEntry;
use App\Models\MeaningGroup;
use App\Models\User;
use App\Services\FigureMeaningService;
use App\Support\BaseMeaningCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeaningGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_groups_are_seeded_with_cards_and_assigned_to_packs(): void
    {
        $tarot = MeaningGroup::query()->where('slug', BaseMeaningCatalog::TAROT_SLUG)->first();
        $oracle = MeaningGroup::query()->where('slug', BaseMeaningCatalog::ORACLE_SLUG)->first();

        $this->assertNotNull($tarot);
        $this->assertNotNull($oracle);
        $this->assertSame(CardPack::TYPE_TAROT, $tarot->type);
        $this->assertSame(CardPack::TYPE_ORACLE, $oracle->type);
        $this->assertGreaterThanOrEqual(22, $tarot->entries()->count());
        $this->assertSame(22, $oracle->entries()->count());
        $this->assertDatabaseHas('meaning_entries', [
            'meaning_group_id' => $tarot->id,
            'figure_id' => '00-the-fool',
        ]);
        $this->assertDatabaseHas('meaning_entries', [
            'meaning_group_id' => $oracle->id,
            'figure_id' => 'sun',
        ]);
        $this->assertSame($tarot->id, CardPack::query()->where('slug', 'tarot')->value('meaning_group_id'));
        $this->assertSame($oracle->id, CardPack::query()->where('slug', 'egi-orakulum')->value('meaning_group_id'));
        $this->assertNull(CardPack::query()->where('slug', 'cigany-kartya')->value('meaning_group_id'));
    }

    public function test_admin_can_create_copy_and_delete_a_group(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meaning-groups.store'), [
                'name_hu' => 'Saját tarot',
                'name_en' => 'My tarot',
                'slug' => 'sajat-tarot',
                'type' => CardPack::TYPE_TAROT,
                'description_hu' => 'Teszt',
                'description_en' => 'Test',
                'entries' => [
                    [
                        'figure_id' => '00-the-fool',
                        'name_hu' => 'A Bolond',
                        'name_en' => 'The Fool',
                        'meaning_hu' => 'kezdés',
                        'meaning_en' => 'start',
                        'description_hu' => 'Magyar leírás.',
                        'description_en' => 'English description.',
                    ],
                ],
            ])
            ->assertRedirect();

        $group = MeaningGroup::query()->where('slug', 'sajat-tarot')->firstOrFail();
        $this->assertSame(1, $group->entries()->count());

        $this->actingAs($admin)
            ->post(route('admin.meaning-groups.copy', $group))
            ->assertRedirect();

        $copy = MeaningGroup::query()->where('slug', 'sajat-tarot-2')->first();
        $this->assertNotNull($copy);
        $this->assertSame(1, $copy->entries()->count());
        $this->assertSame($admin->id, $copy->owner_id);

        $this->actingAs($admin)
            ->delete(route('admin.meaning-groups.destroy', $copy))
            ->assertRedirect(route('admin.meaning-groups.index'));

        $this->assertDatabaseMissing('meaning_groups', ['id' => $copy->id]);
        $this->assertDatabaseHas('meaning_groups', ['id' => $group->id]);
    }

    public function test_pack_can_select_a_meaning_group(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MeaningGroup::factory()->oracle()->create(['name_hu' => 'Egyedi orákulum']);
        $pack = CardPack::query()->where('slug', 'egi-orakulum')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.packs.update', $pack), [
                'name_hu' => $pack->name_hu,
                'name_en' => $pack->name_en,
                'slug' => $pack->slug,
                'description_hu' => (string) $pack->description_hu,
                'description_en' => (string) $pack->description_en,
                'type' => CardPack::TYPE_ORACLE,
                'card_count' => $pack->card_count,
                'style' => (string) $pack->style,
                'owner' => (string) $pack->owner,
                'sort_order' => $pack->sort_order,
                'is_active' => '1',
                'is_default' => $pack->is_default ? '1' : '0',
                'meaning_group_id' => $group->id,
            ])
            ->assertRedirect(route('admin.packs.edit', $pack));

        $this->assertSame($group->id, $pack->fresh()->meaning_group_id);
    }

    public function test_reading_uses_meaning_group_text_before_deck_cache(): void
    {
        $pack = CardPack::query()->where('slug', 'tarot')->firstOrFail();
        MeaningEntry::query()
            ->where('meaning_group_id', $pack->meaning_group_id)
            ->where('figure_id', '01-the-magician')
            ->update([
                'description_hu' => 'Csoportból jött mágus-jelentés.',
                'description_en' => 'Magician meaning from the group.',
            ]);

        $text = app(FigureMeaningService::class)->text('tarot', '01-the-magician', 'hu', 'A Mágus');

        $this->assertSame('Csoportból jött mágus-jelentés.', $text);
    }

    public function test_non_admin_cannot_manage_meaning_groups(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.meaning-groups.index'))
            ->assertForbidden();
    }
}
