<?php

namespace Tests\Feature;

use App\Models\CardPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
