<?php
namespace Tests\Feature;
use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Tests\TestCase;
class UserSubmodulePermissionTest extends TestCase{
 use RefreshDatabase;
 public function test_user_can_access_only_selected_logistics_submodule():void{
  $user=User::factory()->create(['profile'=>'Consulta','permissions'=>['logistics.quotations']]);
  $this->actingAs($user)->get(route('quotations.index'))->assertOk()->assertSee('Cotizaciones');
  $this->actingAs($user)->get(route('business-partners.index'))->assertForbidden();
  $this->actingAs($user)->get(route('purchase-orders.index'))->assertForbidden();
 }
 public function test_legacy_module_permission_keeps_all_existing_access():void{
  $user=User::factory()->create(['profile'=>'Consulta','permissions'=>['logistics']]);
  $this->assertTrue($user->canAccess('logistics.partners'));$this->assertTrue($user->canAccess('logistics.quotations'));$this->assertTrue($user->canAccess('logistics.purchase-orders'));
 }
 public function test_new_user_permissions_are_validated_and_saved_by_submodule():void{
  $admin=User::factory()->create(['profile'=>'Administrador','permissions'=>['users']]);
  $this->actingAs($admin)->post(route('users.store'),['name'=>'Cotizador','email'=>'cotizador@example.com','password'=>'password123','branch'=>'Principal','profile'=>'Consulta','permissions'=>['logistics.quotations'],'is_active'=>'1'])->assertRedirect();
  $this->assertDatabaseHas('users',['email'=>'cotizador@example.com']);
  $created=User::where('email','cotizador@example.com')->firstOrFail();$this->assertSame(['logistics.quotations'],$created->permissions);$this->assertSame('quotations.index',$created->landingRoute());
 }
}
