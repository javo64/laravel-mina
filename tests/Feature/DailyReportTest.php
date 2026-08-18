<?php

namespace Tests\Feature;

use App\Models\DailyReport;
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
        $this->actingAs($assigned)->get(route('daily-reports.fill', $form))->assertOk()
            ->assertSee('GPS obligatorio')->assertSee('guardar la traza del registro');
        $this->actingAs($assigned)->post(route('daily-reports.submit', $form), [
            'responses' => ['observacion' => 'Operación normal'],
        ])->assertSessionHasErrors('latitude');

        $this->actingAs($assigned)->post(route('daily-reports.submit', $form), [
            'latitude' => '-12.046374', 'longitude' => '-77.042793',
            'responses' => ['observacion' => 'Operación normal'],
        ])->assertRedirect(route('daily-reports.records'));
        $this->assertDatabaseHas('daily_reports', ['daily_report_form_id'=>$form->id,'user_id'=>$assigned->id]);
    }

    public function test_mobile_app_only_lists_forms_assigned_to_the_user(): void
    {
        $creator = User::factory()->create(['permissions' => ['daily-reports','users']]);
        $evaluator = User::factory()->create(['permissions' => ['daily-reports']]);
        $assigned = DailyReportForm::create(['name'=>'Cartilla asignada','is_active'=>true,'created_by'=>$creator->id]);
        $hidden = DailyReportForm::create(['name'=>'Cartilla de otro usuario','is_active'=>true,'created_by'=>$creator->id]);
        $assigned->users()->attach($evaluator);

        $this->actingAs($evaluator)->get(route('daily-reports.index'))
            ->assertOk()->assertDontSee('Cartilla asignada')->assertDontSee('Cartilla de otro usuario');
        $this->actingAs($evaluator)->get(route('daily-reports.records'))
            ->assertOk()->assertSee('Cartilla asignada')->assertDontSee('Cartilla de otro usuario');
        $this->actingAs($evaluator)->get(route('mobile.daily-reports.index'))
            ->assertOk()->assertSee('Cartilla asignada')->assertDontSee('Cartilla de otro usuario');
        $this->actingAs($evaluator)->get(route('mobile.daily-reports.fill', $assigned))->assertOk();
        $this->actingAs($evaluator)->get(route('mobile.daily-reports.fill', $hidden))->assertForbidden();
    }

    public function test_records_central_filters_reports_and_exposes_gps_points(): void
    {
        $admin = User::factory()->create(['permissions' => ['daily-reports','users']]);
        $evaluator = User::factory()->create(['name' => 'Evaluador Mina', 'permissions' => ['daily-reports']]);
        $form = DailyReportForm::create(['name'=>'Control con GPS','is_active'=>true,'created_by'=>$admin->id]);
        $report = DailyReport::create([
            'daily_report_form_id'=>$form->id,'user_id'=>$evaluator->id,'reported_at'=>'2026-08-18 10:30:00',
            'latitude'=>'-12.0463740','longitude'=>'-77.0427930','responses'=>['turno'=>'Día'],
        ]);

        $this->actingAs($admin)->get(route('daily-reports.records', [
            'form_id'=>$form->id,'date'=>'2026-08-18','user_id'=>$evaluator->id,
        ]))->assertOk()->assertSee('Control con GPS')->assertSee($evaluator->name)
            ->assertSee('-12.046374')->assertSee('Google Maps')->assertSee('Mapa de puntos registrados')
            ->assertSee('record-detail-'.$report->id)->assertSee('Información completa enviada desde la cartilla')
            ->assertSee('Día')->assertSee('Exportar Excel');
    }

    public function test_admin_can_export_filtered_records_as_a_valid_excel_file(): void
    {
        $admin = User::factory()->create(['permissions' => ['daily-reports','users']]);
        $evaluator = User::factory()->create(['name' => 'Evaluador Mina', 'permissions' => ['daily-reports']]);
        $form = DailyReportForm::create(['name'=>'Control con GPS','is_active'=>true,'created_by'=>$admin->id]);
        $form->fields()->create(['field_key'=>'turno','name'=>'Turno','type'=>'text','section'=>'Datos generales']);

        DailyReport::create([
            'daily_report_form_id'=>$form->id,'user_id'=>$evaluator->id,'reported_at'=>'2026-08-18 10:30:00',
            'latitude'=>'-12.0463740','longitude'=>'-77.0427930','responses'=>['turno'=>'Día'],
        ]);
        DailyReport::create([
            'daily_report_form_id'=>$form->id,'user_id'=>$evaluator->id,'reported_at'=>'2026-08-17 10:30:00',
            'responses'=>['turno'=>'Noche fuera del filtro'],
        ]);

        $response = $this->actingAs($admin)->get(route('daily-reports.export', [
            'form_id'=>$form->id,'date'=>'2026-08-18','user_id'=>$evaluator->id,
        ]));

        $response->assertOk()->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($sheet);
        $this->assertStringContainsString('REGISTRO DE CARTILLAS', $sheet);
        $this->assertStringContainsString('Control con GPS', $sheet);
        $this->assertStringContainsString('Evaluador Mina', $sheet);
        $this->assertStringContainsString('Día', $sheet);
        $this->assertStringNotContainsString('Noche fuera del filtro', $sheet);
    }
}
