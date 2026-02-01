<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;

#[Controller(prefix: '/test')]
class TestSetupController extends AbstractController
{
    /**
     * Setup test accounts for race condition testing
     */
    #[PostMapping(path: '/setup')]
    public function setup(): ResponseInterface
    {
        try {
            // Truncate tables
            Db::statement('SET FOREIGN_KEY_CHECKS = 0');
            Db::table('account_withdraw_pix')->truncate();
            Db::table('account_withdraw')->truncate();
            Db::table('account_pix')->truncate();
            Db::table('account')->truncate();
            Db::statement('SET FOREIGN_KEY_CHECKS = 1');

            // Create race condition test accounts
            $accounts = [
                [
                    'id' => '00000000-0000-0000-0000-000000000001',
                    'cpf' => '00000000001',
                    'name' => 'Race Test Account 1',
                    'balance' => 1000.00,
                    'locked' => false,
                    'locked_at' => null,
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000002',
                    'cpf' => '00000000002',
                    'name' => 'Race Test Account 2',
                    'balance' => 500.00,
                    'locked' => false,
                    'locked_at' => null,
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000003',
                    'cpf' => '00000000003',
                    'name' => 'Low Balance Account',
                    'balance' => 50.00,
                    'locked' => false,
                    'locked_at' => null,
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000004',
                    'cpf' => '00000000004',
                    'name' => 'Zero Balance Account',
                    'balance' => 0.00,
                    'locked' => false,
                    'locked_at' => null,
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000005',
                    'cpf' => '00000000005',
                    'name' => 'Locked Account',
                    'balance' => 1000.00,
                    'locked' => true,
                    'locked_at' => now(),
                ],
            ];

            $pixKeys = [
                ['id' => '10000000-0000-0000-0000-000000000001', 'account_id' => '00000000-0000-0000-0000-000000000001', 'type' => 'email', 'key' => 'race1@test.com'],
                ['id' => '10000000-0000-0000-0000-000000000002', 'account_id' => '00000000-0000-0000-0000-000000000002', 'type' => 'email', 'key' => 'race2@test.com'],
                ['id' => '10000000-0000-0000-0000-000000000003', 'account_id' => '00000000-0000-0000-0000-000000000003', 'type' => 'email', 'key' => 'lowbalance@test.com'],
                ['id' => '10000000-0000-0000-0000-000000000004', 'account_id' => '00000000-0000-0000-0000-000000000004', 'type' => 'email', 'key' => 'zerobalance@test.com'],
                ['id' => '10000000-0000-0000-0000-000000000005', 'account_id' => '00000000-0000-0000-0000-000000000005', 'type' => 'email', 'key' => 'locked@test.com'],
            ];

            Db::table('account')->insert($accounts);
            Db::table('account_pix')->insert($pixKeys);

            return $this->response->json([
                'success' => true,
                'message' => 'Test data created successfully',
                'data' => [
                    'accounts' => count($accounts),
                    'pix_keys' => count($pixKeys),
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->response->json([
                'success' => false,
                'message' => 'Failed to create test data: ' . $e->getMessage(),
            ])->withStatus(500);
        }
    }

    /**
     * Reset a specific account balance
     */
    #[PostMapping(path: '/reset-account/{accountId}')]
    public function resetAccount(string $accountId): ResponseInterface
    {
        $balances = [
            '00000000-0000-0000-0000-000000000001' => 1000.00,
            '00000000-0000-0000-0000-000000000002' => 500.00,
            '00000000-0000-0000-0000-000000000003' => 50.00,
            '00000000-0000-0000-0000-000000000004' => 0.00,
            '00000000-0000-0000-0000-000000000005' => 1000.00,
        ];

        $balance = $balances[$accountId] ?? 1000.00;
        $isLocked = $accountId === '00000000-0000-0000-0000-000000000005';

        Db::table('account')->where('id', $accountId)->update([
            'balance' => $balance,
            'locked' => $isLocked,
            'locked_at' => $isLocked ? now() : null,
        ]);

        return $this->response->json([
            'success' => true,
            'message' => 'Account reset successfully',
            'data' => [
                'account_id' => $accountId,
                'balance' => $balance,
                'locked' => $isLocked,
            ]
        ]);
    }

    /**
     * Reset all test accounts to initial state
     */
    #[PostMapping(path: '/reset-all')]
    public function resetAll(): ResponseInterface
    {
        $resets = [
            ['id' => '00000000-0000-0000-0000-000000000001', 'balance' => 1000.00, 'locked' => false],
            ['id' => '00000000-0000-0000-0000-000000000002', 'balance' => 500.00, 'locked' => false],
            ['id' => '00000000-0000-0000-0000-000000000003', 'balance' => 50.00, 'locked' => false],
            ['id' => '00000000-0000-0000-0000-000000000004', 'balance' => 0.00, 'locked' => false],
            ['id' => '00000000-0000-0000-0000-000000000005', 'balance' => 1000.00, 'locked' => true],
        ];

        foreach ($resets as $reset) {
            Db::table('account')->where('id', $reset['id'])->update([
                'balance' => $reset['balance'],
                'locked' => $reset['locked'],
                'locked_at' => $reset['locked'] ? now() : null,
            ]);
        }

        // Also clear withdrawals
        Db::statement('SET FOREIGN_KEY_CHECKS = 0');
        Db::table('account_withdraw_pix')->truncate();
        Db::table('account_withdraw')->truncate();
        Db::statement('SET FOREIGN_KEY_CHECKS = 1');

        return $this->response->json([
            'success' => true,
            'message' => 'All accounts reset to initial state',
        ]);
    }

    /**
     * Get current state of all test accounts
     */
    #[GetMapping(path: '/accounts')]
    public function getAccounts(): ResponseInterface
    {
        $accounts = Db::table('account')
            ->select(['id', 'name', 'balance', 'locked'])
            ->orderBy('id')
            ->get();

        return $this->response->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Generate stress test accounts
     */
    #[PostMapping(path: '/generate/{count}')]
    public function generate(int $count): ResponseInterface
    {
        $count = min($count, 100000); // Limit to 100k

        try {
            $batchSize = 1000;
            $created = 0;

            for ($i = 0; $i < $count; $i += $batchSize) {
                $batch = min($batchSize, $count - $i);
                $accounts = [];
                $pixKeys = [];

                for ($j = 0; $j < $batch; $j++) {
                    $index = $i + $j + 1000; // Offset to avoid conflicts
                    $accountId = Uuid::uuid7()->toString();
                    $pixId = Uuid::uuid7()->toString();

                    $accounts[] = [
                        'id' => $accountId,
                        'cpf' => str_pad((string) ($index + 10000000000), 11, '0', STR_PAD_LEFT),
                        'name' => "Stress Test User {$index}",
                        'balance' => round(mt_rand(10000, 1000000) / 100, 2),
                        'locked' => false,
                        'locked_at' => null,
                    ];

                    $pixKeys[] = [
                        'id' => $pixId,
                        'account_id' => $accountId,
                        'type' => 'email',
                        'key' => "stress{$index}@test.com",
                    ];
                }

                Db::table('account')->insert($accounts);
                Db::table('account_pix')->insert($pixKeys);
                $created += $batch;
            }

            return $this->response->json([
                'success' => true,
                'message' => "Generated {$created} accounts",
                'data' => ['count' => $created],
            ]);
        } catch (\Throwable $e) {
            return $this->response->json([
                'success' => false,
                'message' => 'Failed: ' . $e->getMessage(),
            ])->withStatus(500);
        }
    }
}
