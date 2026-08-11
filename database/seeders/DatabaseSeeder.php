<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@mina.local'],
            ['name'=>'Javier Alcántara','password'=>'Admin2026','branch'=>'Almacén principal','profile'=>'Administrador','is_active'=>true,'permissions'=>['products','requirements','approvals','logistics','users']]
        );
    }
}
