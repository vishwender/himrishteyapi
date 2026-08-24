<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $applications = [
            [
                'name' => 'Himrishtey',
                'code' => 'himrishtey',
                'database' => 'himrishteymain_base',
                'status' => 'active',
            ],
            [
                'name' => 'Gall Pakki',
                'code' => 'gallpakki',
                'database' => 'himrishteymain_gallpakki',
                'status' => 'active',
            ],
            [
                'name' => 'Devbhoomi',
                'code' => 'devbhoomi',
                'database' => 'himrishteymain_devbhoomi',
                'status' => 'active',
            ],
            [
                'name' => 'Dogri Rishtey',
                'code' => 'dogririshtey',
                'database' => 'himrishteymain_dogririshtey',
                'status' => 'active',
            ],
        ];

        foreach ($applications as $application) {
            Application::updateOrCreate(
                [
                    'code' => $application['code'],
                ],
                $application
            );
        }
    }
}
