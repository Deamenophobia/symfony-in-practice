<?php

// Deliberately uses PHP's default scalar coercion mode for the language experiment.
function weakMoney(): App\Domain\Money
{
    return new App\Domain\Money('1250');
}
