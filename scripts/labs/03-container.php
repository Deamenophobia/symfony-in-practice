<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Domain\PriceCatalog;
use App\Tests\Fixtures\DiscountCatalog;
use App\Tests\Fixtures\FixedCatalog;
use App\Tests\Fixtures\Quote;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\RuntimeException as ContainerException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

function pricingContainer(?string $implementation): ContainerBuilder
{
    $container = new ContainerBuilder();
    foreach ([FixedCatalog::class, DiscountCatalog::class, Quote::class] as $class) {
        $container->register($class, $class)->setAutowired(true)->setPublic(Quote::class === $class);
    }
    if (null !== $implementation) {
        $container->setAlias(PriceCatalog::class, $implementation);
    }
    $container->compile();

    return $container;
}

try {
    pricingContainer(null);
    throw new RuntimeException('Ambiguous interface should fail.');
} catch (ContainerException $error) {
    labAssert(str_contains($error->getMessage(), PriceCatalog::class), 'Unexpected container failure.');
    $results = ['missing_alias' => $error::class];
}
$container = pricingContainer(FixedCatalog::class);
$results['fixed_total'] = $container->get(Quote::class)->total();
$results['discount_total'] = pricingContainer(DiscountCatalog::class)->get(Quote::class)->total();
try {
    $container->get(FixedCatalog::class);
    throw new RuntimeException('Private service should not be fetched.');
} catch (ServiceNotFoundException $error) {
    $results['private_lookup'] = $error::class;
}
$directory = dirname(__DIR__, 2).'/.runtime';
if (!is_dir($directory)) {
    mkdir($directory);
}
file_put_contents($directory.'/PricingLabContainer.php', (new PhpDumper($container))->dump(['class' => 'PricingLabContainer']));
require $directory.'/PricingLabContainer.php';
$results['dumped_total'] = (new PricingLabContainer())->get(Quote::class)->total();
labAssert(2500 === $results['fixed_total'] && 2000 === $results['discount_total'] && 2500 === $results['dumped_total'], 'Container wiring result changed.');
labEvidence('03-container', $results);
