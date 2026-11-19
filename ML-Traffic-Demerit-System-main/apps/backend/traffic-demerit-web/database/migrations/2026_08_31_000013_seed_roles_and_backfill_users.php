<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['role_name' => 'admin', 'permission_level' => 100, 'role_description' => 'System administrator'],
            ['role_name' => 'officer', 'permission_level' => 70, 'role_description' => 'Field traffic enforcement officer'],
            ['role_name' => 'motorist', 'permission_level' => 30, 'role_description' => 'Registered motorist user'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role['role_name']],
                [
                    'permission_level' => $role['permission_level'],
                    'role_description' => $role['role_description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('users')->whereNull('registered_on')->update(['registered_on' => now()]);

        DB::table('users')->where('role', 'admin')->update([
            'role_id' => DB::table('roles')->where('role_name', 'admin')->value('id'),
        ]);
        DB::table('users')->where('role', 'officer')->update([
            'role_id' => DB::table('roles')->where('role_name', 'officer')->value('id'),
        ]);
        DB::table('users')->where('role', 'motorist')->update([
            'role_id' => DB::table('roles')->where('role_name', 'motorist')->value('id'),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->update(['role_id' => null]);
        DB::table('roles')->whereIn('role_name', ['admin', 'officer', 'motorist'])->delete();
    }
};
