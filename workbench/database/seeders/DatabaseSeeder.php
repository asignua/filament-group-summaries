<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'emma@example.com'], ['name' => 'Emma Demo', 'password' => 'password', 'is_active' => true]);

        $rows = [
            ['paid', 'Studio licence', 1200, 3], ['paid', 'Support plan', 480, 1], ['paid', 'Training day', 900, 2],
            ['open', 'Hosting, annual', 360, 1], ['open', 'Extra seats', 150, 5], ['open', 'Migration', 2400, 1], ['open', 'Audit', 700, 1],
            ['void', 'Duplicate order', 90, 1], ['void', 'Cancelled trial', 30, 1],
            ['refunded', 'Faulty adapter', 45, 1], ['refunded', 'Wrong size', 120, 2],
        ];

        foreach ($rows as $i => [$status, $reference, $amount, $quantity]) {
            Order::create(['reference' => $reference, 'status' => $status, 'amount' => $amount, 'quantity' => $quantity]);
        }
    }
}
