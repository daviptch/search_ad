<?php
/**
 * Cron - Calculate the balance of the Google Search Ads Monthly Budget
 *			also it sends a Zendesk ticket to support in case the budget reaches $0
 */

require_once(__DIR__ . '/../includes/include.php');

$execution_id = cron_start(157);

$send_email = false;
$to = environment::is_production() ? 'email@dreamscape.com' : DEVELOPERS_EMAILS;

$yesterday_date = date('Y-m-d', strtotime('NOW - 1 days'));

// find all the google ads products regardless their status, which are the products with the budget and client id registered
// google product registered in our system and balance should be already added
$query = "	SELECT product_data_id, member_id, currency_id, client_id,monthly_budget_product_data_id, balance, product_status  
			FROM
			(
				SELECT gpd.product_data_id, gpd.member_id, gpd.currency_id, ga.client_id ,	ga2mb.monthly_budget_product_data_id, 
					gapc.budget_credit as balance,
					(
						SELECT product_status 
						FROM sYra.generic_products_data 
						WHERE product_data_id = monthly_budget_product_data_id
					) as product_status
				FROM sYra.generic_products_data gpd
					LEFT JOIN sYra.google_advertising ga ON ga.product_data_id = gpd.product_data_id
					LEFT JOIN sYra.google_advertising_to_monthly_budget ga2mb ON ga2mb.product_data_id = gpd.product_data_id
					LEFT JOIN sYra.google_advertising_product_credit gapc ON gapc.product_data_id = ga2mb.monthly_budget_product_data_id
				WHERE gpd.product_type_id = 29 AND ga.client_id IS NOT NULL
				GROUP BY gpd.product_data_id
			) aux_table
			WHERE product_status IN (3,10)
			ORDER BY product_data_id";

$db_syssql = db::connect('syssql');
$result = $db_syssql
	->select($query)
	->execute();

if ($result->row_count() > 0) {
	$product_row = $result->fetch_all();

	foreach ($product_row as $product_key => $product_data) {
		$query = "	SELECT SUM(COST) AS 'last_cost' 
					FROM AWReports.AW_ReportAd aw 
					WHERE aw.account_id = :client_id AND CAST(DAY AS DATE) = :yesterday_date";

		$bind = array(
			':client_id' => $product_data['client_id'],
			':yesterday_date' => $yesterday_date,
		);

		$result = db::connect('awreports')
			->select($query)
			->binds($bind)
			->execute()
			->fetch();

		if ($result['last_cost']) {
			$product_row[$product_key]['last_cost'] = $result['last_cost'];
		} else {
			unset($product_row[$product_key]);
		}
	}

	$subject = "Search Ad - Search Ads products budget has ended";
	$message = "The following Search Ad products have reached balance 0 (zero)." . PHP_EOL . PHP_EOL;

	foreach ($product_row as $product_details) {
		$new_balance = $product_details['balance'] - $product_details['last_cost'];
		$new_balance = $new_balance < 0 ? 0 : number_format($new_balance, 2) ;

		$add_product_to_message= false;

		if ($product_details['balance'] != '' ) {
			try {
				$values = array (
					'budget_credit' => $new_balance,
					'last_adwords_date' => $yesterday_date,
				);

				$db_syssql
					->update()
					->table('sYra.google_advertising_product_credit')
					->values($values)
					->where('product_data_id = :monthly_budget_product_data_id')
					->binds(array(
						':monthly_budget_product_data_id' => $product_details['monthly_budget_product_data_id'],
					))
					->execute();
			} catch (DatabaseException $dbe) {
				//Show generic error for db class exceptions
				throw new Exception('An error has occurred, please try again');
			}

			if ($new_balance <= 0) {
				$add_product_to_message = true;
			}
		}

		if ($add_product_to_message) {
			$message .= "Product ID: {$product_details['product_data_id']}" . PHP_EOL .
						"Member ID: {$product_details['member_id']}" . PHP_EOL .
						"Client ID: {$product_details['client_id']}" . PHP_EOL . PHP_EOL;
			$send_email = true;
		}
	}

	if ($send_email) {
		notification::send($to, FROM_EMAIL, FROM_EMAIL_NAME, $subject, $message);
	}
}

cron_commit($execution_id);
