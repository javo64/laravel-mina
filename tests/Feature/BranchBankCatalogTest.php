<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\BusinessPartner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchBankCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_user_can_create_branch_and_child_warehouse(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $this->actingAs($user)->get(route('branches.index'))->assertOk()->assertSee('Sucursal principal');
        $this->actingAs($user)->post(route('branches.store'), ['name'=>'Sucursal Norte','code'=>'NOR'])->assertRedirect();
        $branch = Branch::where('name','Sucursal Norte')->firstOrFail();
        $this->actingAs($user)->post(route('warehouses.store'), ['branch_id'=>$branch->id,'name'=>'Almacén Norte','code'=>'AN-01'])->assertRedirect();
        $this->assertDatabaseHas('warehouses',['branch_id'=>$branch->id,'name'=>'Almacén Norte']);
    }

    public function test_logistics_user_can_register_provider_bank_and_account(): void
    {
        $user = User::factory()->create(['permissions' => ['logistics']]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20999999991','name'=>'Proveedor Banco SAC','is_active'=>true]);
        $this->actingAs($user)->postJson(route('banks.store'), ['name'=>'Banco de Prueba'])
            ->assertCreated()->assertJsonPath('name', 'Banco de Prueba')->assertJsonPath('code', 'BAN-0001');
        $bank = Bank::where('name','Banco de Prueba')->firstOrFail();
        $this->actingAs($user)->postJson(route('business-partners.bank-accounts.store'), [
            'business_partner_id'=>$supplier->id,'bank_id'=>$bank->id,'account_type'=>'Cuenta Interbancaria','currency'=>'USD','account_number'=>'002999999999','holder_name'=>'Proveedor Banco SAC',
        ])->assertCreated()->assertJsonPath('bank', 'Banco de Prueba')->assertJsonPath('currency', 'USD');
        $this->assertDatabaseHas('bank_accounts',['business_partner_id'=>$supplier->id,'bank_id'=>$bank->id,'account_type'=>'Cuenta Interbancaria','currency'=>'USD']);
    }
}
