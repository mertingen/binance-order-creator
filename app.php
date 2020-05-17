<?php

require 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

if (isset($_ENV['IS_DEVELOPMENT'])){
    $dotenv = new Dotenv();
    $dotenv->load(__DIR__.'/.env');
}

try {

	$api = new Binance\API($_ENV['API_KEY'],$_ENV['API_SECRET_KEY']);

	$api->useServerTime();

	$percentage = 10;

	$ticker = $api->prices();
	$balances = $api->balances($ticker);
	$btcBalance = $balances['BTC']['btcTotal'];

	$orders = [];
	$counter = 0;
	foreach ($ticker as $key => $value) {
		if ((strpos($key, 'BTC') > -1) && $value >= 0.00000100 && $value <= 0.00002500) {
			$changeStatus = $api->prevDay($key);
			$priceChangePercent = intval($changeStatus['priceChangePercent']);
			$counter++;
			echo $counter . "-) " . $key . " - %" . $priceChangePercent . PHP_EOL;
			if ($priceChangePercent < 10){
				//getting %10 of the float number
				$orders[$key] =  number_format($value - (($percentage / 100) * $value), 8);
			}
		}
	}

	asort($orders);
	$symbols = array_keys($orders);
	echo "Total orders: " . count($orders) . PHP_EOL;


	$scopeVariables = array(
		'api' => $api,
		'btcBalance' => $btcBalance,
		'orders' => $orders
	);

	echo "started listening..." . PHP_EOL;
	$api->trades($symbols, function($api, $symbol, $trades) use ($scopeVariables) {
		if (isset($scopeVariables['orders'][$symbol])){
			if ($trades['price'] <= $scopeVariables['orders'][$symbol]){
				$quantity = intval($scopeVariables['btcBalance'] / $trades['price']);
				#$price = floatval($trades['price']) - 0.00000010;
				#$price = number_format($price, 8);
				$order = $scopeVariables['api']->buy($symbol, $quantity, $scopeVariables['orders'][$symbol]);
				print_r($order);
				die;
			}
		}
	});
} catch(\Exception $e){
	print_r($e->getMessage()); die;
}