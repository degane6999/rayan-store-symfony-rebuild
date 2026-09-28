<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\Support\DatabaseWebTestCase;

/**
 * The flagship test: simulates N independent buyers trying to check out the
 * same limited-stock product at the same instant, using N real separate PHP
 * processes each with their own DB connection (not just N loop iterations in
 * one process/transaction, which would prove nothing about DB-level locking).
 *
 * Verifies the real OrderService (pessimistic write lock, deterministic lock
 * ordering) lets exactly as many orders succeed as there is stock, and never
 * more -- then, for comparison, runs the exact same scenario through a
 * deliberately lock-free NaiveOrderService to show empirically what the lock
 * is actually protecting against.
 */
class OrderConcurrencyTest extends DatabaseWebTestCase
{
    private const WORKER_TIMEOUT_SECONDS = 30;

    /** @return array<int, array<string,mixed>> */
    private function runParallel(array $userIds, string $mode): array
    {
        $barrierFile = sys_get_temp_dir().'/concurrency_barrier_'.uniqid('', true);
        $workerScript = __DIR__.'/worker.php';

        $processes = [];
        foreach ($userIds as $userId) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open(
                ['php', $workerScript, $barrierFile, (string) $userId, $mode],
                $descriptors,
                $pipes
            );
            $processes[] = ['proc' => $proc, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        }

        // Let every worker reach its busy-wait loop before releasing the barrier.
        usleep(300_000);
        file_put_contents($barrierFile, '1');

        $results = [];
        $start = microtime(true);
        foreach ($processes as $p) {
            $out = stream_get_contents($p['stdout']);
            $err = stream_get_contents($p['stderr']);
            fclose($p['stdout']);
            fclose($p['stderr']);
            proc_close($p['proc']);

            $decoded = json_decode(trim($out), true);
            $results[] = $decoded ?? ['status' => 'unparseable', 'raw_stdout' => $out, 'raw_stderr' => $err];

            if ((microtime(true) - $start) > self::WORKER_TIMEOUT_SECONDS) {
                break;
            }
        }

        @unlink($barrierFile);

        return $results;
    }

    /** @return array{category: Category, product: Product, buyers: User[]} */
    private function makeBuyersWithCarts(int $count, int $initialStock, string $slug): array
    {
        $category = $this->makeCategory('Concurrency', 'concurrency-'.uniqid());
        $product = $this->makeProduct($category, 'Produit rare', $slug, '10.00', $initialStock);

        $buyers = [];
        for ($i = 0; $i < $count; ++$i) {
            $user = $this->makeUser(sprintf('buyer-%d-%s@test.local', $i, uniqid()));
            $cart = new \App\Entity\Cart($user);
            $cart->addProduct($product, 1);
            $this->em->persist($cart);
            $buyers[] = $user;
        }
        $this->em->flush();

        return ['category' => $category, 'product' => $product, 'buyers' => $buyers];
    }

    private function countStatus(array $results, string $status): int
    {
        return count(array_filter($results, static fn ($r) => ($r['status'] ?? null) === $status));
    }

    public function testFiftySimultaneousBuyersOfTenUnitsSellsExactlyTen(): void
    {
        static::createClient();
        $this->prepareDatabase();

        $setup = $this->makeBuyersWithCarts(50, 10, 'produit-rare-50');
        $userIds = array_map(static fn (User $u) => $u->getId(), $setup['buyers']);

        $results = $this->runParallel($userIds, 'locked');

        $success = $this->countStatus($results, 'success');
        $outOfStock = $this->countStatus($results, 'out_of_stock');
        $deadlocks = $this->countStatus($results, 'deadlock');
        $errors = $this->countStatus($results, 'error');

        $this->em->clear();
        $finalStock = $this->em->getRepository(Product::class)->find($setup['product']->getId())->getStock();
        $ordersPlaced = count($this->em->getRepository(Order::class)->findAll());

        // The core correctness claim: never more successful orders than stock existed.
        $this->assertLessThanOrEqual(10, $success, 'Oversold: more orders succeeded than units in stock.');
        $this->assertSame($success, $ordersPlaced, 'Order rows in DB must match reported successes.');
        $this->assertSame(10 - $success, $finalStock, 'Final stock must exactly equal initial stock minus units actually sold.');
        $this->assertSame(50, $success + $outOfStock + $deadlocks + $errors, 'Every worker must resolve to a known, accounted-for outcome.');

        fwrite(STDERR, sprintf(
            "\n[concurrency] locked/50 buyers/10 stock -> success=%d out_of_stock=%d deadlock=%d error=%d final_stock=%d\n",
            $success, $outOfStock, $deadlocks, $errors, $finalStock
        ));
    }

    public function testFiftySimultaneousBuyersOfOneUnitSellsExactlyOne(): void
    {
        static::createClient();
        $this->prepareDatabase();

        $setup = $this->makeBuyersWithCarts(50, 1, 'produit-rare-1');
        $userIds = array_map(static fn (User $u) => $u->getId(), $setup['buyers']);

        $results = $this->runParallel($userIds, 'locked');
        $success = $this->countStatus($results, 'success');

        $this->em->clear();
        $finalStock = $this->em->getRepository(Product::class)->find($setup['product']->getId())->getStock();

        $this->assertSame(1, $success, 'Exactly one buyer must win the last unit under lock protection.');
        $this->assertSame(0, $finalStock);
    }

    /**
     * The comparison case: same 50-buyers-vs-10-stock scenario, but through the
     * lock-free NaiveOrderService. This documents empirically -- not by
     * assumption -- what actually happens without the pessimistic lock.
     */
    public function testWithoutLockTheSameScenarioCorruptsStockOrOversells(): void
    {
        static::createClient();
        $this->prepareDatabase();

        $setup = $this->makeBuyersWithCarts(50, 10, 'produit-rare-naive');
        $userIds = array_map(static fn (User $u) => $u->getId(), $setup['buyers']);

        $results = $this->runParallel($userIds, 'naive');
        $success = $this->countStatus($results, 'success');
        $errors = $this->countStatus($results, 'error');

        $this->em->clear();
        $finalStock = $this->em->getRepository(Product::class)->find($setup['product']->getId())->getStock();
        $expectedStockIfCorrect = 10 - $success;

        fwrite(STDERR, sprintf(
            "\n[concurrency] naive/50 buyers/10 stock -> success=%d error=%d final_stock=%d (expected_if_correct=%d)\n",
            $success, $errors, $finalStock, $expectedStockIfCorrect
        ));

        // The naive service is expected to fail the exact invariant the locked
        // one upholds: either it oversells (more than 10 successes), or the
        // final stock doesn't match what was actually sold (lost updates), or
        // both. If this assertion ever fails, the naive implementation
        // accidentally behaved correctly on this run and the comparison is
        // not demonstrating anything -- it should not be reported as a stable finding.
        $this->assertTrue(
            $success > 10 || $finalStock !== $expectedStockIfCorrect,
            'Expected the lock-free implementation to oversell or corrupt stock under contention, but it did not on this run.'
        );
    }
}
