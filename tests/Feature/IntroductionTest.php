<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Services\CardDrawProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntroductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_introduction_page_is_a_menu_destination_and_saves_profile(): void
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

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('messages.intro_menu'), false)
            ->assertSee(route('account.introduction'), false);

        $this->actingAs($user)
            ->get(route('account.introduction'))
            ->assertOk()
            ->assertSee(__('messages.intro_text'), false);

        $this->actingAs($user)
            ->put(route('account.introduction.update'), [
                'birth_at' => '1990-05-01T14:30',
                'gender' => 'female',
                'introduction' => 'Kertész vagyok, és a döntéseimet lassan hozom.',
            ])
            ->assertRedirect(route('account.introduction'));

        $user->refresh();
        $this->assertSame('female', $user->gender);
        $this->assertSame('Kertész vagyok, és a döntéseimet lassan hozom.', $user->introduction);
        $this->assertSame('1990-05-01 12:30:00', $user->birth_at?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_introduction_rejects_more_than_200_characters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.introduction'))
            ->put(route('account.introduction.update'), [
                'introduction' => str_repeat('á', 201),
            ])
            ->assertRedirect(route('account.introduction'))
            ->assertSessionHasErrors('introduction');
    }
}
