<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSpreadPreference extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'card_back_id',
        'packs',
    ];

    protected function casts(): array
    {
        return [
            'packs' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function cardBack(): BelongsTo
    {
        return $this->belongsTo(CardPack::class, 'card_back_id');
    }

    /**
     * Save one user's spread choice. An expert can call this for any user later.
     *
     * @param  array{game?: int|null, packs?: array<string, string>, back?: int|null}  $selection
     */
    public static function remember(User $user, array $selection): self
    {
        $gameId = (int) ($selection['game'] ?? 0);
        $gameId = $gameId > 0 && Game::query()->active()->whereKey($gameId)->exists()
            ? $gameId
            : null;

        $backId = (int) ($selection['back'] ?? 0);
        $backId = $backId > 0 && CardPack::query()->active()->ofType(CardPack::TYPE_CARD_BACK)->whereKey($backId)->exists()
            ? $backId
            : null;

        $packs = [];
        foreach ((array) ($selection['packs'] ?? []) as $type => $slug) {
            if (! in_array($type, CardPack::FIGURE_TYPES, true)) {
                continue;
            }
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            $pack = CardPack::query()->active()->ofType($type)->where('slug', $slug)->first();
            if ($pack !== null) {
                $packs[$type] = $pack->slug;
            }
        }

        return static::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'game_id' => $gameId,
                'card_back_id' => $backId,
                'packs' => $packs,
            ]
        );
    }

    /**
     * The signed-in user's saved choice. Guests keep the browser session.
     * A user without a saved choice follows the site default, not another visitor's session.
     *
     * @return array{game: int|null, packs: array<string, string>, back: int|null}
     */
    public static function selectionFor(?User $user): array
    {
        if ($user === null) {
            $selection = session('spread', []);

            return self::normalize(is_array($selection) ? $selection : []);
        }

        $stored = static::query()->where('user_id', $user->id)->first();
        $selection = $stored?->toSelection() ?? [
            'game' => null,
            'packs' => [],
            'back' => null,
        ];
        session(['spread' => $selection]);

        return $selection;
    }

    /**
     * @return array{game: int|null, packs: array<string, string>, back: int|null}
     */
    public function toSelection(): array
    {
        return self::normalize([
            'game' => $this->game_id,
            'packs' => $this->packs,
            'back' => $this->card_back_id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array{game: int|null, packs: array<string, string>, back: int|null}
     */
    private static function normalize(array $selection): array
    {
        $packs = [];
        foreach ((array) ($selection['packs'] ?? []) as $type => $slug) {
            $slug = trim((string) $slug);
            if ($slug !== '') {
                $packs[(string) $type] = $slug;
            }
        }

        $gameId = (int) ($selection['game'] ?? 0);
        $backId = (int) ($selection['back'] ?? 0);

        return [
            'game' => $gameId > 0 ? $gameId : null,
            'packs' => $packs,
            'back' => $backId > 0 ? $backId : null,
        ];
    }
}
