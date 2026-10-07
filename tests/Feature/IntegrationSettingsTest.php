<?php

namespace Tests\Feature;

use App\Models\DocumentApiSetting;
use App\Models\OpenAiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreadable_legacy_credentials_do_not_break_configuration_views(): void
    {
        $admin = User::factory()->create(['profile' => 'Administrador']);
        DB::table('openai_settings')->insert([
            'id' => 1, 'api_key' => 'legacy-ciphertext-that-cannot-be-decrypted', 'model' => 'gpt-5.6-sol',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('document_api_settings')->insert([
            'id' => 1, 'url' => 'https://api.example.test/{document}', 'token' => 'legacy-token-that-cannot-be-decrypted',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertFalse(OpenAiSetting::current()->hasApiKey());
        $this->assertTrue(OpenAiSetting::current()->hasUnreadableApiKey());
        $this->assertFalse(DocumentApiSetting::current()->hasToken());
        $this->assertTrue(DocumentApiSetting::current()->hasUnreadableToken());

        $this->actingAs($admin)->get(route('settings.openai.edit'))
            ->assertOk()->assertSee('no puede leerse');
        $this->actingAs($admin)->get(route('settings.document-api.edit'))
            ->assertOk()->assertSee('no puede leerse');
    }
}
