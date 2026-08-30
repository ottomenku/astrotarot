<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_hu');
            $table->string('name_en');
            $table->unsignedInteger('price_huf')->default(0);
            $table->string('period', 16)->default('monthly');
            $table->unsignedInteger('credits')->default(0);
            $table->longText('body_hu')->nullable();
            $table->longText('body_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('can_export_pdf')->default(false);
            $table->boolean('can_view_history')->default(false);
            $table->boolean('has_astromotto')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('packages')->insert([
            [
                'slug' => 'free',
                'name_hu' => 'Free',
                'name_en' => 'Free',
                'price_huf' => 0,
                'period' => 'monthly',
                'credits' => 0,
                'body_hu' => '<ul><li>Regisztrációkor 10 kredit</li><li>Minden nap +1 kredit bejelentkezéskor</li><li>Alap olvasatok</li></ul>',
                'body_en' => '<ul><li>10 credits on signup</li><li>+1 credit each day on login</li><li>Basic readings</li></ul>',
                'is_active' => true,
                'sort_order' => 1,
                'can_export_pdf' => false,
                'can_view_history' => false,
                'has_astromotto' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'premium',
                'name_hu' => 'Premium',
                'name_en' => 'Premium',
                'price_huf' => 1600,
                'period' => 'monthly',
                'credits' => 50,
                'body_hu' => '<ul><li>Havi 50 kredit</li><li>PDF mentés</li><li>Kérdések naplózása és visszanézése</li></ul>',
                'body_en' => '<ul><li>50 credits / month</li><li>PDF export</li><li>Question log &amp; history</li></ul>',
                'is_active' => true,
                'sort_order' => 2,
                'can_export_pdf' => true,
                'can_view_history' => true,
                'has_astromotto' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'expert',
                'name_hu' => 'Expert',
                'name_en' => 'Expert',
                'price_huf' => 2500,
                'period' => 'monthly',
                'credits' => 100,
                'body_hu' => '<ul><li>Havi 100 kredit</li><li>Minden Premium előny</li><li>Prémium hozzáférés az AstroMotto asztrológiai oldalhoz</li></ul>',
                'body_en' => '<ul><li>100 credits / month</li><li>All Premium benefits</li><li>Premium access to the AstroMotto astrology site</li></ul>',
                'is_active' => true,
                'sort_order' => 3,
                'can_export_pdf' => true,
                'can_view_history' => true,
                'has_astromotto' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
