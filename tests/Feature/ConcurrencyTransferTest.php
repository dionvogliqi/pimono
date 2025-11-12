<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_concurrent_transfers_do_not_overdraft_mysql_only(): void
    {
        if (env('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Concurrency test requires MySQL (InnoDB).');
        }
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension is required for this test.');
        }

        $sender = User::factory()->create(['balance' => '1015.0000']);
        $receiver = User::factory()->create(['balance' => '0.0000']);

        $transfers = 100;

        $pids = [];
        for ($i = 0; $i < $transfers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Could not fork');
            } elseif ($pid === 0) {
                // child process
                try {
                    // each child needs its own DB connection
                    DB::disconnect();
                    $service = new TransferService();
                    $service->transfer(User::find($sender->id), $receiver->id, '10.00');
                } catch (\Throwable $e) {
                    // ignore failures; some could fail if exceeded funds
                }
                exit(0);
            } else {
                $pids[] = $pid;
            }
        }

        // wait for all children
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $sender->refresh();
        $receiver->refresh();

        // Exactly 100 successful transfers should have occurred
        $this->assertSame('0.0000', (string) $sender->balance);
        $this->assertSame('1000.0000', (string) $receiver->balance);
    }
}
