<?php

namespace Tests\Feature;

use App\Models\DailyReportForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_digital_form_with_fields_and_users(): void
    {
        $admin = User::factory()->create(['permissions' => ['daily-reports','users']]);
        $evaluator = User::factory()->create(['permissions' => ['daily-reports']]);

        $this->actingAs($admin)->post(route('daily-reports.store'), [
            'name' => 'Parte diario de planta',
            'scope' => 'Planta',
            'is_active' => '1',
            'use_gps' => '1',
            'user_ids' => [$evaluator->id],
            'fields' => [
                ['name'=>'Fecha','field_key'=>'fecha','section'=>'Datos generales','type'=>'date','is_required'=>'1','options'=>''],
                ['name'=>'Toneladas','field_key'=>'toneladas','section'=>'Producción','type'=>'decimal','is_required'=>'1','options'=>'','settings'=>json_encode(['counter_buttons'=>true,'has_limits'=>true,'min'=>0,'max'=>5000])],
                ['name'=>'Horas','field_key'=>'horas','section'=>'Producción','type'=>'decimal','is_required'=>'1','options'=>''],
                ['name'=>'Equipo','field_key'=>'equipo','section'=>'Producción','type'=>'options','is_required'=>'1','options'=>"SCOOP\nDUMPER\nOTROS"],
                ['name'=>'Rendimiento','field_key'=>'rendimiento','section'=>'Producción','type'=>'formula','formula'=>'{toneladas} / {horas}','options'=>''],
            ],
        ])->assertRedirect();

        $form = DailyReportForm::firstOrFail();
        $this->assertTrue($form->use_gps);
        $this->assertCount(5, $form->fields);
        $this->assertSame(['SCOOP','DUMPER','OTROS'], $form->fields->firstWhere('field_key', 'equipo')->options);
        $this->assertTrue($form->fields->firstWhere('field_key', 'toneladas')->settings['counter_buttons']);
        $this->assertTrue($form->users->contains($evaluator));
    }

    public function test_only_assigned_users_can_fill_a_form_and_gps_is_required(): void
    {
        $creator = User::factory()->create(['permissions' => ['daily-reports','users']]);
        $assigned = User::factory()->create(['permissions' => ['daily-reports']]);
        $unassigned = User::factory()->create(['permissions' => ['daily-reports']]);
        $form = DailyReportForm::create(['name'=>'Inspección','is_active'=>true,'use_gps'=>true,'created_by'=>$creator->id]);
        $form->fields()->create(['field_key'=>'observacion','name'=>'Observación','type'=>'text','section'=>'General','is_required'=>true]);
        $form->users()->attach($assigned);

        $this->actingAs($unassigned)->get(route('daily-reports.fill', $form))->assertForbidden();
        $this->actingAs($assigned)->get(route('daily-reports.fill', $form))->assertOk();
        $this->actingAs($assigned)->post(route('daily-reports.submit', $form), [
            'responses' => ['observacion' => 'Operación normal'],
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($assigned)->post(route('daily-reports.submit', $form), [
            'latitude' => '-12.046374', 'longitude' => '-77.042793',
            'responses' => ['observacion' => 'Operación normal'],
        ])->assertRedirect(route('daily-reports.index'));
        $this->assertDatabaseHas('daily_reports', ['daily_report_form_id'=>$form->id,'user_id'=>$assigned->id]);
    }
}
