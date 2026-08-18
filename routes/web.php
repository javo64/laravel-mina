<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\BusinessPartnerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReceptionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\MeasurementUnitController;
use App\Http\Controllers\OpenAiSettingController;
use App\Http\Controllers\DocumentApiSettingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\ResponsibleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DailyReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $module = auth()->user()->permissions[0] ?? 'products';

    return redirect()->route(match ($module) {
        'users' => 'users.index',
        'logistics' => 'business-partners.index',
        'daily-reports' => 'daily-reports.index',
        default => $module.'.index',
    });
});
Route::get('/movil', fn () => auth()->check() ? redirect()->route('mobile.daily-reports.index') : redirect()->route('login', ['mobile' => 1]))->name('mobile.home');
Route::middleware('guest')->group(function () { Route::get('/login',[AuthController::class,'showLogin'])->name('login'); Route::post('/login',[AuthController::class,'login'])->name('login.attempt'); });
Route::middleware('auth')->group(function () {
    Route::post('/logout',[AuthController::class,'logout'])->name('logout');
    Route::get('/productos',[ProductController::class,'index'])->name('products.index'); Route::post('/productos',[ProductController::class,'store'])->name('products.store'); Route::put('/productos/{product}',[ProductController::class,'update'])->name('products.update'); Route::delete('/productos/{product}',[ProductController::class,'destroy'])->name('products.destroy');
    Route::get('/recepcion-productos',[ProductReceptionController::class,'index'])->name('product-receptions.index'); Route::post('/recepcion-productos',[ProductReceptionController::class,'store'])->name('product-receptions.store');
    Route::post('/recepcion-productos/analizar-documento',[ProductReceptionController::class,'analyzeDocument'])->name('product-receptions.analyze-document');
    Route::post('/categorias-producto',[ProductCategoryController::class,'store'])->name('product-categories.store'); Route::delete('/categorias-producto/{category}',[ProductCategoryController::class,'destroy'])->name('product-categories.destroy');
    Route::post('/unidades-medida',[MeasurementUnitController::class,'store'])->name('measurement-units.store'); Route::delete('/unidades-medida/{unit}',[MeasurementUnitController::class,'destroy'])->name('measurement-units.destroy');
    Route::get('/requerimientos',[RequirementController::class,'index'])->name('requirements.index'); Route::post('/requerimientos',[RequirementController::class,'store'])->name('requirements.store');
    Route::post('/responsables',[ResponsibleController::class,'store'])->name('responsibles.store'); Route::delete('/responsables/{responsible}',[ResponsibleController::class,'destroy'])->name('responsibles.destroy');
    Route::post('/areas',[AreaController::class,'store'])->name('areas.store'); Route::delete('/areas/{area}',[AreaController::class,'destroy'])->name('areas.destroy');
    Route::post('/proyectos',[ProjectController::class,'store'])->name('projects.store'); Route::delete('/proyectos/{project}',[ProjectController::class,'destroy'])->name('projects.destroy');
    Route::get('/aprobaciones',[ApprovalController::class,'index'])->name('approvals.index'); Route::post('/aprobaciones/{requirement}',[ApprovalController::class,'decide'])->name('approvals.decide');
    Route::get('/usuarios',[UserController::class,'index'])->name('users.index'); Route::post('/usuarios',[UserController::class,'store'])->name('users.store'); Route::put('/usuarios/{user}',[UserController::class,'update'])->name('users.update');
    Route::get('/configuracion/openai',[OpenAiSettingController::class,'edit'])->name('settings.openai.edit'); Route::put('/configuracion/openai',[OpenAiSettingController::class,'update'])->name('settings.openai.update');
    Route::get('/logistica/clientes-proveedores',[BusinessPartnerController::class,'index'])->name('business-partners.index'); Route::post('/logistica/clientes-proveedores/consultar',[BusinessPartnerController::class,'lookup'])->name('business-partners.lookup'); Route::post('/logistica/clientes-proveedores',[BusinessPartnerController::class,'store'])->name('business-partners.store'); Route::put('/logistica/clientes-proveedores/{businessPartner}',[BusinessPartnerController::class,'update'])->name('business-partners.update');
    Route::get('/configuracion/api-documentos',[DocumentApiSettingController::class,'edit'])->name('settings.document-api.edit'); Route::put('/configuracion/api-documentos',[DocumentApiSettingController::class,'update'])->name('settings.document-api.update');
    Route::get('/parte-diario-digital',[DailyReportController::class,'index'])->name('daily-reports.index');
    Route::get('/parte-diario-digital/registro-cartillas',[DailyReportController::class,'records'])->name('daily-reports.records');
    Route::get('/parte-diario-digital/registro-cartillas/exportar',[DailyReportController::class,'exportRecords'])->name('daily-reports.export');
    Route::get('/parte-diario-digital/cartillas/crear',[DailyReportController::class,'create'])->name('daily-reports.create');
    Route::post('/parte-diario-digital/cartillas',[DailyReportController::class,'store'])->name('daily-reports.store');
    Route::get('/parte-diario-digital/cartillas/{dailyReportForm}/editar',[DailyReportController::class,'edit'])->name('daily-reports.edit');
    Route::put('/parte-diario-digital/cartillas/{dailyReportForm}',[DailyReportController::class,'update'])->name('daily-reports.update');
    Route::get('/parte-diario-digital/cartillas/{dailyReportForm}/previsualizar',[DailyReportController::class,'preview'])->name('daily-reports.preview');
    Route::get('/parte-diario-digital/cartillas/{dailyReportForm}/registrar',[DailyReportController::class,'fill'])->name('daily-reports.fill');
    Route::post('/parte-diario-digital/cartillas/{dailyReportForm}/registrar',[DailyReportController::class,'submit'])->name('daily-reports.submit');
    Route::get('/movil/cartillas',[DailyReportController::class,'mobileIndex'])->name('mobile.daily-reports.index');
    Route::get('/movil/cartillas/{dailyReportForm}',[DailyReportController::class,'mobileFill'])->name('mobile.daily-reports.fill');
    Route::post('/movil/cartillas/{dailyReportForm}',[DailyReportController::class,'submit'])->name('mobile.daily-reports.submit');
});
