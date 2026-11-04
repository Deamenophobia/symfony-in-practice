<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';
require __DIR__.'/weak-caller.php';

use App\Domain\Money;
use App\Domain\OrderStatus;
use App\Dto\CreateOrder;
use App\Entity\Order;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Validator\ValidatorInterface;

$price = new Money(1250);
$results = ['total_cents' => $price->times(2)->cents, 'original_cents' => $price->cents];
try {
    new Money('1250');
    throw new RuntimeException('Strict call accepted string.');
} catch (TypeError $error) {
    $results['strict_string'] = $error::class;
}
$results['weak_string_cents'] = weakMoney()->cents;
try {
    $price->cents = 1;
    throw new RuntimeException('Readonly changed.');
} catch (Error $error) {
    $results['readonly_write'] = $error::class;
}
try {
    new Money(-1);
    throw new RuntimeException('Negative amount accepted.');
} catch (InvalidArgumentException $error) {
    $results['negative_amount'] = $error::class;
}
$input = new CreateOrder('COFFEE', 0);
$results['constructor_quantity'] = $input->quantity;
$results['range_attributes'] = count((new ReflectionProperty(CreateOrder::class, 'quantity'))->getAttributes(Range::class));
$results['validator_violations'] = count(labService(ValidatorInterface::class)->validate($input));
$order = new Order(1, 'COFFEE', 2, $price);
$order->confirm();
$results['confirmed_status'] = $order->status()->value;
try {
    $order->confirm();
    throw new RuntimeException('Repeated confirmation accepted.');
} catch (DomainException $error) {
    $results['repeated_confirmation'] = $error::class;
}
$results['unknown_enum'] = OrderStatus::tryFrom('missing');
labAssert(2500 === $results['total_cents'] && 1250 === $results['original_cents'] && 1250 === $results['weak_string_cents'] && 1 === $results['validator_violations'] && 1 === $results['range_attributes'] && null === $results['unknown_enum'], 'PHP boundary result changed.');
labEvidence('02-modern-php', $results);
