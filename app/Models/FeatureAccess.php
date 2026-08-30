<?php

namespace App\Models;

use App\Support\PackageBody;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FeatureAccess extends Model
{
    protected $table = 'feature_access';

    protected $fillable = [
        'key',
        'label_hu',
        'label_en',
        'credit_cost',
        'logic',
    ];

    protected function casts(): array
    {
        return [
            'credit_cost' => 'integer',
        ];
    }

    public function label(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $value = $locale === 'en' ? (string) $this->label_en : (string) $this->label_hu;

        return $value !== '' ? $value : PackageBody::perkLabel($this->key, $locale === 'en' ? 'en' : 'hu');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'feature_access_package');
    }
}
