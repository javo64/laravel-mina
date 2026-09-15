<?php

namespace App\Support;

class PermissionCatalog
{
    public const MODULES = [
        'warehouse'=>['label'=>'Almacén','children'=>[
            'warehouse.products'=>'Productos y servicios','warehouse.receptions'=>'Recepción de productos','warehouse.inventory'=>'Inventario',
            'warehouse.structure'=>'Empresas, sucursales y almacenes','warehouse.requirements'=>'Requerimientos','warehouse.approvals'=>'Aprobaciones',
        ]],
        'logistics'=>['label'=>'Logística','children'=>[
            'logistics.partners'=>'Clientes y proveedores','logistics.quotations'=>'Cotizaciones','logistics.purchase-orders'=>'Órdenes de compra',
        ]],
        'costs'=>['label'=>'Costos','children'=>['costs.cost-centers'=>'Centro de costos']],
        'daily-reports'=>['label'=>'Parte Diario Digital','children'=>['daily-reports.forms'=>'Cartillas y registros']],
        'administration'=>['label'=>'Administración','children'=>[
            'administration.users'=>'Usuarios','administration.openai'=>'OpenAI','administration.document-api'=>'API Documentos',
        ]],
    ];

    public const LEGACY = [
        'products'=>['warehouse.products','warehouse.receptions','warehouse.inventory','warehouse.structure'],
        'requirements'=>['warehouse.requirements'],'approvals'=>['warehouse.approvals'],
        'logistics'=>['logistics.partners','logistics.quotations','logistics.purchase-orders'],
        'costs'=>['costs.cost-centers'],'daily-reports'=>['daily-reports.forms'],
        'users'=>['administration.users','administration.openai','administration.document-api'],
    ];

    public static function keys(): array { return collect(self::MODULES)->flatMap(fn($module)=>array_keys($module['children']))->values()->all(); }
    public static function label(string $key): string { foreach(self::MODULES as $module) if(isset($module['children'][$key])) return $module['children'][$key]; return $key; }
    public static function grants(string $stored, string $requested): bool { return $stored===$requested || in_array($requested,self::LEGACY[$stored]??[],true); }
}
