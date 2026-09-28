<?php

declare(strict_types=1);

/**
 * CLI worker spawned by OrderConcurrencyTest via proc_open(). Each worker is
 * an independent PHP process with its own DB connection (this is what makes
 * the test a real test of row-level locking, not just PHP-level mutual
 * exclusion within a single process/request).
 *
 * Usage: php worker.php <barrier_file> <user_id> <mode: locked|naive>
 *
 * Each worker:
 *   1. Boots its own kernel/container.
 *   2. Waits for the barrier file to exist (busy-wait, sub-millisecond poll) so
 *      all workers hit placeOrder() at effectively the same instant.
 *   3. Buys whatever is already in that user's cart.
 *   4. Prints a single line of JSON with the outcome.
 */

require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Entity\User;
use App\Kernel;
use App\Service\OrderService;
use App\Tests\Support\NaiveOrderService;
use Symfony\Component\Dotenv\Dotenv;

[$script, $barrierFile, $userId, $mode] = $argv + [null, null, null, null];

(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container') ?? $kernel->getContainer();

$em = $container->get('doctrine')->getManager();
$user = $em->getRepository(User::class)->find((int) $userId);

// Busy-wait for the barrier: every worker released within microseconds of
// each other, to actually create genuine DB-level contention.
while (!file_exists($barrierFile)) {
    usleep(500);
}

$result = ['user_id' => (int) $userId, 'mode' => $mode];

try {
    if ('naive' === $mode) {
        /** @var NaiveOrderService $service */
        $service = new NaiveOrderService(
            $em,
            $container->get('App\Repository\CartRepository'),
            $container->get('App\Service\StockService'),
            $container->get('App\Service\SluggerService'),
            $container->get('App\Service\EmailService'),
        );
    } else {
        /** @var OrderService $service */
        $service = $container->get(OrderService::class);
    }

    $order = $service->placeOrder($user, [
        'fullName' => 'Concurrency Test', 'address' => 'x', 'city' => 'x', 'postalCode' => 'x', 'country' => 'FR',
    ]);
    $result['status'] = 'success';
    $result['order_number'] = $order->getOrderNumber();
} catch (App\Exception\OutOfStockException $e) {
    $result['status'] = 'out_of_stock';
    $result['message'] = $e->getMessage();
} catch (Doctrine\DBAL\Exception\DeadlockException $e) {
    $result['status'] = 'deadlock';
    $result['message'] = $e->getMessage();
} catch (Throwable $e) {
    $result['status'] = 'error';
    $result['message'] = get_class($e).': '.$e->getMessage();
}

fwrite(STDOUT, json_encode($result).\PHP_EOL);
