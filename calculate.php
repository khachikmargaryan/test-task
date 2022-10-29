<?php
$options = ['file:'];

$values = getopt('', $options);
$csv = file($values['file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$rates = json_decode(file_get_contents('https://developers.paysera.com/tasks/api/currency-exchange-rates'), true)['rates'];

$operations = array();
$depositPercentage = 0.03;
$withdrawPercentagePrivate = 0.3;
$withdrawPercentageBusiness = 0.5;

foreach ($csv as $key => $value) {
    $operations[$key] = str_getcsv($value);
}

$operationsByWeek = array();
$usersFreeOperationIsAvailable = array();
foreach ($operations as $operation) {
    $day = date('w', strtotime($operation[0]));
    $week_start = date('Y-m-d', strtotime($operation[0] . '-' . ($day === '0' ? 6 : $day - 1) . ' days'));
    $week_end = date('Y-m-d', strtotime($week_start . '+ 6 days'));

    $operationsByWeek[$week_start . '/' . $week_end][] = $operation;
    $usersFreeOperationIsAvailable[$week_start . '/' . $week_end][$operation[1]] = true;
}

foreach ($operationsByWeek as $key => $operations) {
    $userWeekOperationsAmounts = array();
    foreach ($operations as $operation) {
        $commission = 0;
        if ($operation[3] === 'deposit') {
            $commission = ($depositPercentage * $operation[4] / $rates[$operation[5]]) / 100;
            echo number_format($commission * $rates[$operation[5]], 2, '.', '') . "\n";
        } else {
            if ($operation[2] === 'business') {
                $commission = ($withdrawPercentageBusiness * $operation[4] / $rates[$operation[5]]) / 100;
                echo number_format($commission * $rates[$operation[5]], 2, '.', '') . "\n";
            } else {
                $userWeekOperationsAmounts[$operation[1]][] = $operation[4] / $rates[$operation[5]];

                if ($operation[4] / $rates[$operation[5]] > 1000) {
                    $freeAmount = $usersFreeOperationIsAvailable[$key][$operation[1]] ? 1000 : 0;
                    $commission = ($withdrawPercentagePrivate * (($operation[4] / $rates[$operation[5]]) - $freeAmount)) / 100;
                    $usersFreeOperationIsAvailable[$key][$operation[1]] = false;
                    echo number_format($commission * $rates[$operation[5]], 2, '.', '') . "\n";
                } elseif (count($userWeekOperationsAmounts[$operation[1]]) > 3) {
                    $commission = ($withdrawPercentagePrivate * $operation[4] / $rates[$operation[5]]) / 100;
                    $usersFreeOperationIsAvailable[$key][$operation[1]] = false;
                    echo number_format($commission * $rates[$operation[5]], 2, '.', '') . "\n";
                } elseif(array_sum($userWeekOperationsAmounts[$operation[1]]) > 1000) {
                    $amount = $usersFreeOperationIsAvailable[$key][$operation[1]] ? array_sum($userWeekOperationsAmounts[$operation[1]]) - 1000 : $operation[4];
                    $commission = ($withdrawPercentagePrivate * $amount / $rates[$operation[5]]) / 100;
                    $usersFreeOperationIsAvailable[$key][$operation[1]] = false;
                    echo number_format($commission * $rates[$operation[5]], 2, '.', '') . "\n";
                } else {
                    echo number_format(0, 2, '.', '') . "\n";
                }
            }
        }
    }
}