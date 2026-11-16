<?php

namespace Tests\Feature;

use App\Models\BirthProfile;
use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstroDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_user_can_open_saved_astro_data_from_the_menu(): void
    {
        Template::factory()->create([
            'name' => 'Art Deco',
            'slug' => 'art-deco',
            'is_active' => true,
        ]);
        $this->mock(CardDrawProfileService::class, function ($mock) {
            $mock->shouldReceive('forNow')->andReturn([
                'sun_sign' => 'leo',
                'element' => 'tűz',
                'modality' => 'fő',
                'tone' => 'pozitív',
                'variant' => 'positive',
                'tones' => ['pozitív'],
                'variants' => ['positive'],
                'slots' => ['leo'],
                'chart_mode' => 'now',
                'datetime_utc' => '2026-08-10T12:00:00Z',
            ]);
        });

        $user = User::factory()->create([
            'name' => 'Olvasó Kata',
            'birth_at' => '1990-05-01 12:30:00',
            'birth_country' => 'Magyarország',
            'birth_city' => 'Jászberény',
            'birth_latitude' => 47.5,
            'birth_longitude' => 19.91,
            'gender' => 'female',
            'introduction' => 'Kertész vagyok.',
        ]);
        BirthProfile::query()->create([
            'user_id' => $user->id,
            'label' => 'Anna',
            'birth_at' => '1991-06-15 12:00:00',
            'birth_country' => 'Magyarország',
            'birth_city' => 'Szeged',
            'introduction' => 'A testvérem.',
        ]);
        $other = User::factory()->create(['name' => 'Másik Olvasó', 'birth_city' => null]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.astro_menu'), false)
            ->assertSee(route('account.astro'), false);

        $this->actingAs($user)
            ->get(route('account.astro'))
            ->assertOk()
            ->assertSee('Olvasó Kata', false)
            ->assertSee('1990-05-01 14:30', false)
            ->assertSee('Jászberény', false)
            ->assertSee('Kertész vagyok.', false)
            ->assertSee(__('messages.intro_gender_female'), false)
            ->assertSee('Anna', false)
            ->assertSee('1991-06-15 14:00', false)
            ->assertSee('Szeged', false)
            ->assertSee('A testvérem.', false)
            ->assertDontSee('Titkosváros', false);

        $this->actingAs($other)
            ->get(route('account.astro'))
            ->assertOk()
            ->assertSee(__('messages.astro_self_empty'), false)
            ->assertSee(__('messages.astro_relations_empty'), false)
            ->assertDontSee('Jászberény', false)
            ->assertDontSee('Anna', false);
    }
}
