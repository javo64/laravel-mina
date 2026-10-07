<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\BusinessPartner;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchBankCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_user_can_create_branch_and_child_warehouse(): void
    {
        $user = User::factory()->create(['permissions' => ['products']]);
        $plant=Company::where('name','PLANTA FABULOSA')->firstOrFail();
        $mine=Company::where('name','MINA CAROLINA JE')->firstOrFail();
        $this->actingAs($user)->get(route('branches.index'))->assertOk()->assertSee('PLANTA FABULOSA')->assertSee('MINA CAROLINA JE')->assertSee('Sucursal principal');
        $this->actingAs($user)->post(route('companies.activate'), ['company_id'=>$mine->id])->assertRedirect();
        $this->actingAs($user)->post(route('branches.store'), ['company_id'=>$mine->id,'name'=>'Sucursal Norte'])->assertRedirect();
        $branch = Branch::where('name','Sucursal Norte')->firstOrFail();
        $this->assertSame($mine->id,$branch->company_id);
        $this->assertSame($plant->id,Branch::where('name','Sucursal principal')->firstOrFail()->company_id);
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

    public function test_bank_accounts_can_be_edited_with_the_business_partner(): void
    {
        $user = User::factory()->create(['permissions' => ['logistics']]);
        $bank = Bank::create(['name'=>'Banco Central','code'=>'BAN-0100','is_active'=>true]);
        $supplier = BusinessPartner::create(['type'=>'Proveedor','document_type'=>'RUC','document_number'=>'20999999992','name'=>'Proveedor Editable SAC','is_active'=>true]);
        $account = BankAccount::create(['business_partner_id'=>$supplier->id,'bank_id'=>$bank->id,'bank_name'=>$bank->name,'account_type'=>'Cuenta Corriente','currency'=>'PEN','account_number'=>'100200300','holder_name'=>$supplier->name,'is_active'=>true]);

        $this->actingAs($user)->get(route('business-partners.index'))->assertOk()
            ->assertSee('100200300')->assertSee('Cuentas bancarias');

        $this->actingAs($user)->put(route('business-partners.update', $supplier), [
            'type'=>'Proveedor','document_type'=>'RUC','document_number'=>$supplier->document_number,
            'name'=>$supplier->name,'is_active'=>'1',
            'bank_accounts'=>[['id'=>$account->id,'bank_id'=>$bank->id,'account_type'=>'Cuenta Interbancaria','currency'=>'USD','account_number'=>'100200301','holder_name'=>'Nuevo titular','is_active'=>'1']],
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_accounts',['id'=>$account->id,'account_type'=>'Cuenta Interbancaria','currency'=>'USD','account_number'=>'100200301','holder_name'=>'Nuevo titular']);
    }
}
