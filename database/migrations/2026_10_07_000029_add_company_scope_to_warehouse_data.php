<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['products', 'warehouses', 'product_receptions', 'inventory_stocks', 'inventory_movements'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });
        }

        DB::table('warehouses')->orderBy('id')->get(['id', 'branch_id'])->each(function ($warehouse): void {
            $companyId = DB::table('branches')->where('id', $warehouse->branch_id)->value('company_id');
            if ($companyId) DB::table('warehouses')->where('id', $warehouse->id)->update(['company_id' => $companyId]);
        });
        $defaultCompanyId = DB::table('companies')->orderBy('id')->value('id');
        foreach (['products', 'product_receptions'] as $tableName) DB::table($tableName)->whereNull('company_id')->update(['company_id' => $defaultCompanyId]);
        DB::table('inventory_stocks')->orderBy('id')->get(['id', 'warehouse_id'])->each(function ($stock) use ($defaultCompanyId): void {
            $companyId = DB::table('warehouses')->where('id', $stock->warehouse_id)->value('company_id') ?: $defaultCompanyId;
            DB::table('inventory_stocks')->where('id', $stock->id)->update(['company_id' => $companyId]);
        });
        DB::table('inventory_movements')->orderBy('id')->get(['id', 'source_warehouse_id', 'destination_warehouse_id'])->each(function ($movement) use ($defaultCompanyId): void {
            $warehouseId = $movement->destination_warehouse_id ?: $movement->source_warehouse_id;
            $companyId = $warehouseId ? DB::table('warehouses')->where('id', $warehouseId)->value('company_id') : $defaultCompanyId;
            DB::table('inventory_movements')->where('id', $movement->id)->update(['company_id' => $companyId ?: $defaultCompanyId]);
        });
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('company_id'));
        }
    }
};
