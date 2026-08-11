<?php

namespace Tests\Feature;

use App\Models\OpenAiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpenAiSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_store_encrypted_openai_credentials(): void
    {
        $user = User::factory()->create(['permissions' => ['users']]);
        $plainKey = 'sk-proj-example-secret-key-1234567890';

        $response = $this->actingAs($user)->put(route('settings.openai.update'), [
            'api_key' => $plainKey,
            'model' => 'gpt-5.6-sol',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('settings.openai.edit'));
        $setting = OpenAiSetting::current();
        $this->assertSame($plainKey, $setting->api_key);
        $this->assertTrue($setting->is_active);
        $this->assertNotSame($plainKey, DB::table('openai_settings')->value('api_key'));
    }

    public function test_non_administrator_cannot_access_openai_credentials(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);

        $this->actingAs($user)->get(route('settings.openai.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('settings.openai.update'), [
            'api_key' => 'sk-proj-example-secret-key-1234567890',
            'model' => 'gpt-5.6-sol',
            'is_active' => '1',
        ])->assertForbidden();
    }
}
