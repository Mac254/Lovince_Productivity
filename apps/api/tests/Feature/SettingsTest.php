<?php

namespace Tests\Feature;

use App\Models\User;
use App\Settings\Setting;
use App\Settings\SettingsModule;
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_module_is_found_and_joined_with_defaults(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.modules.*.key', ['general', 'appearance', 'clock', 'timer', 'journal'])
            ->assertJsonPath('data.values.appearance.theme', 'dark')
            ->assertJsonPath('data.values.timer.chime', true)
            ->assertJsonPath('data.modules.1.settings.0.options.1', ['value' => 'light', 'label' => 'Light'])
            ->assertJsonPath('data.modules.2.settings.0.listed', false);
    }

    public function test_changes_are_validated_merged_and_stored_as_differences_from_the_defaults(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patchJson('/api/v1/settings', ['appearance' => ['theme' => 'light'], 'clock' => ['wallpaper' => 'custom:4']])
            ->assertOk()
            ->assertJsonPath('data.values.appearance.theme', 'light')
            ->assertJsonPath('data.values.appearance.reduce_motion', false)
            ->assertJsonPath('data.values.clock.wallpaper', 'custom:4');
        $this->patchJson('/api/v1/settings', ['timer' => ['auto_break' => true], 'appearance' => ['theme' => 'dark']])->assertOk();

        $this->assertSame(['clock' => ['wallpaper' => 'custom:4'], 'timer' => ['auto_break' => true]], $user->fresh()->settings);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('meta.settings.timer.auto_break', true);

        $this->patchJson('/api/v1/settings', ['appearance' => ['theme' => 'neon']])->assertJsonValidationErrors('appearance.theme');
        $this->patchJson('/api/v1/settings', ['timer' => ['chime' => 'yes']])->assertJsonValidationErrors('timer.chime');
        $this->patchJson('/api/v1/settings', ['clock' => ['wallpaper' => 'custom:x']])->assertJsonValidationErrors('clock.wallpaper');
        $this->patchJson('/api/v1/settings', ['appearance' => ['font' => 'mono']])->assertJsonValidationErrors('appearance.font');
        $this->patchJson('/api/v1/settings', ['billing' => ['plan' => 'pro']])->assertJsonValidationErrors('billing');
        $this->patchJson('/api/v1/settings', [])->assertJsonValidationErrors('settings');
    }

    public function test_a_module_or_everything_resets_to_the_defaults(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['settings' => ['clock' => ['ticking' => true], 'journal' => ['font' => 'sans']]])->save();
        $this->actingAs($user);

        $this->deleteJson('/api/v1/settings?module=clock')->assertOk()
            ->assertJsonPath('data.values.clock.ticking', false)
            ->assertJsonPath('data.values.journal.font', 'sans');
        $this->deleteJson('/api/v1/settings?module=nope')->assertJsonValidationErrors('module');
        $this->deleteJson('/api/v1/settings')->assertOk();

        $this->assertNull($user->fresh()->settings);
    }

    public function test_stored_values_a_module_no_longer_accepts_fall_back_to_the_default(): void
    {
        $registry = app(SettingsRegistry::class);

        $values = $registry->resolve(['appearance' => ['theme' => 'sepia', 'gone' => true], 'removed' => ['x' => 1]]);

        $this->assertSame('dark', $values['appearance']['theme']);
        $this->assertArrayNotHasKey('gone', $values['appearance']);
        $this->assertArrayNotHasKey('removed', $values);
    }

    public function test_modules_with_the_same_key_are_refused(): void
    {
        $module = new class extends SettingsModule
        {
            public function key(): string
            {
                return 'twin';
            }

            public function label(): string
            {
                return 'Twin';
            }

            public function settings(): array
            {
                return [Setting::toggle('on', 'On', false)];
            }
        };

        $this->expectException(\LogicException::class);
        new SettingsRegistry([$module::class, $module::class]);
    }

    public function test_settings_need_a_session(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
        $this->patchJson('/api/v1/settings', ['appearance' => ['theme' => 'light']])->assertUnauthorized();
    }
}
