<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        $users = [
            // 一般ユーザー（admin=0）
            ['name' => 'a', 'email' => 'a@a', 'password' => Hash::make('password'), 'admin' => 0],
            ['name' => 'General User 2', 'email' => 'user2@example.com', 'password' => Hash::make('password'), 'admin' => 0],
            ['name' => 'General User 3', 'email' => 'user3@example.com', 'password' => Hash::make('password'), 'admin' => 0],
            ['name' => 'General User 4', 'email' => 'user4@example.com', 'password' => Hash::make('password'), 'admin' => 0],
            ['name' => 'General User 5', 'email' => 'user5@example.com', 'password' => Hash::make('password'), 'admin' => 0],

            // 管理者（admin=1）
            ['name' => 'admin', 'email' => 'b@b', 'password' => Hash::make('password'), 'admin' => 1],
            ['name' => 'Admin User 2', 'email' => 'admin2@example.com', 'password' => Hash::make('password'), 'admin' => 1],
            ['name' => 'Admin User 3', 'email' => 'admin3@example.com', 'password' => Hash::make('password'), 'admin' => 1],
            ['name' => 'Admin User 4', 'email' => 'admin4@example.com', 'password' => Hash::make('password'), 'admin' => 1],
            ['name' => 'Admin User 5', 'email' => 'admin5@example.com', 'password' => Hash::make('password'), 'admin' => 1],
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']], // emailで一意に
                [
                    'name' => $u['name'],
                    'password' => $u['password'],
                    'admin' => $u['admin'],
                    'email_verified_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
