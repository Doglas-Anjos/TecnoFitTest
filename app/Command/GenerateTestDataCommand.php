<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[Command]
class GenerateTestDataCommand extends HyperfCommand
{
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('test:generate-data');
    }

    public function configure(): void
    {
        parent::configure();
        $this->setDescription('Generate test data for stress testing');
        $this->addArgument('count', InputArgument::OPTIONAL, 'Number of accounts to generate', 1000);
        $this->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Batch size for inserts', 1000);
        $this->addOption('with-race-accounts', 'r', InputOption::VALUE_NONE, 'Include special race condition test accounts');
        $this->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate tables before generating');
    }

    public function handle(): int
    {
        $count = (int) $this->input->getArgument('count');
        $batchSize = (int) $this->input->getOption('batch-size');
        $withRaceAccounts = $this->input->getOption('with-race-accounts');
        $truncate = $this->input->getOption('truncate');

        $this->info("Starting test data generation...");
        $this->info("  Accounts to generate: " . number_format($count));
        $this->info("  Batch size: " . number_format($batchSize));

        if ($truncate) {
            $this->truncateTables();
        }

        $startTime = microtime(true);

        // Generate accounts in batches
        $this->generateAccounts($count, $batchSize);

        // Add special race condition test accounts
        if ($withRaceAccounts) {
            $this->generateRaceConditionAccounts();
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->info("\nGeneration completed!");
        $this->info("  Duration: {$duration} seconds");
        $this->info("  Accounts created: " . number_format($count + ($withRaceAccounts ? 5 : 0)));

        return 0;
    }

    private function truncateTables(): void
    {
        $this->warn("Truncating tables...");

        Db::statement('SET FOREIGN_KEY_CHECKS = 0');
        Db::table('account_withdraw_pix')->truncate();
        Db::table('account_withdraw')->truncate();
        Db::table('account_pix')->truncate();
        Db::table('account')->truncate();
        Db::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->info("Tables truncated.");
    }

    private function generateAccounts(int $count, int $batchSize): void
    {
        $this->info("\nGenerating {$count} accounts...");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $accountBatch = [];
        $pixBatch = [];

        for ($i = 0; $i < $count; $i++) {
            $accountId = Uuid::uuid7()->toString();
            $pixId = Uuid::uuid7()->toString();
            $cpf = str_pad((string) ($i + 10000000000), 11, '0', STR_PAD_LEFT);

            $accountBatch[] = [
                'id' => $accountId,
                'cpf' => $cpf,
                'name' => "Test User {$i}",
                'balance' => round(mt_rand(10000, 1000000) / 100, 2), // R$ 100.00 - R$ 10,000.00
                'locked' => false,
                'locked_at' => null,
            ];

            $pixBatch[] = [
                'id' => $pixId,
                'account_id' => $accountId,
                'type' => 'email',
                'key' => "user{$i}@stresstest.com",
            ];

            // Insert in batches
            if (count($accountBatch) >= $batchSize) {
                $this->insertBatch($accountBatch, $pixBatch);
                $accountBatch = [];
                $pixBatch = [];
                $progressBar->advance($batchSize);
            }
        }

        // Insert remaining
        if (!empty($accountBatch)) {
            $this->insertBatch($accountBatch, $pixBatch);
            $progressBar->advance(count($accountBatch));
        }

        $progressBar->finish();
        $this->line('');
    }

    private function insertBatch(array $accounts, array $pixKeys): void
    {
        Db::table('account')->insert($accounts);
        Db::table('account_pix')->insert($pixKeys);
    }

    private function generateRaceConditionAccounts(): void
    {
        $this->info("\nGenerating race condition test accounts...");

        $raceAccounts = [
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

        $racePixKeys = [
            ['id' => '10000000-0000-0000-0000-000000000001', 'account_id' => '00000000-0000-0000-0000-000000000001', 'type' => 'email', 'key' => 'race1@test.com'],
            ['id' => '10000000-0000-0000-0000-000000000002', 'account_id' => '00000000-0000-0000-0000-000000000002', 'type' => 'email', 'key' => 'race2@test.com'],
            ['id' => '10000000-0000-0000-0000-000000000003', 'account_id' => '00000000-0000-0000-0000-000000000003', 'type' => 'email', 'key' => 'lowbalance@test.com'],
            ['id' => '10000000-0000-0000-0000-000000000004', 'account_id' => '00000000-0000-0000-0000-000000000004', 'type' => 'email', 'key' => 'zerobalance@test.com'],
            ['id' => '10000000-0000-0000-0000-000000000005', 'account_id' => '00000000-0000-0000-0000-000000000005', 'type' => 'email', 'key' => 'locked@test.com'],
        ];

        Db::table('account')->insert($raceAccounts);
        Db::table('account_pix')->insert($racePixKeys);

        $this->info("  Created 5 race condition test accounts");
    }
}
