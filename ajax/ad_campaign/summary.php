<?php

require_once('../../includes/config_reports.php');
require_once('../../includes/config.php');

$template->loadTemplatefile('ad_campaign/summary.tpl');

$client_id = sanitize_int($_GET['client_id']);
$product_data_id = sanitize_int($_GET['product_data_id']);
$report_month = sanitize_int($_GET['report_month']);
$report_year = sanitize_int($_GET['report_year']);
$year_period_start_date = sanitize_sql_string($_GET['year_period_start_date']);
$year_period_end_date = sanitize_sql_string($_GET['year_period_end_date']);

$date = DateTime::createFromFormat("Y-m-d", $year_period_end_date);
$quantity_days = $date->format("d");
$last_month = "";

$measurement_array = array('clicks', 'impressions', 'ctr', 'avg_cpc', 'cost');

// finding the Search Ad product client_id
$query = "	SELECT gpd.currency_id
			FROM sYra.google_advertising ga
				LEFT JOIN sYra.generic_products_data gpd ON gpd.product_data_id = ga.product_data_id
			WHERE ga.client_id = :client_id";

$db_syssql = db::connect('syssql');

$row = $db_syssql
  ->select($query)
  ->binds(array(':client_id' => $client_id,))
  ->execute()
  ->fetch();

$currency_id = $row['currency_id'];

$query = "	SELECT MONTH(awr.DAY) AS month, YEAR(awr.DAY) AS year,
				SUM(awr.clicks) AS clicks,
				SUM(awr.impressions) AS impressions,
				IFNULL((SUM(awr.clicks)/SUM(awr.impressions))*100, 0) AS ctr,
				IFNULL((SUM(awr.cost)/SUM(awr.clicks)), 0) AS avg_cpc,
				SUM(awr.cost) AS cost
			FROM AWReports.AW_ReportAd awr
			WHERE awr.account_id = :client_id
				AND CAST(awr.DAY AS DATE) BETWEEN :year_period_start_date AND :year_period_end_date
				AND awr.DEVICE IS NOT NULL
			GROUP BY YEAR(awr.DAY), MONTH(awr.DAY)
			ORDER BY YEAR(awr.DAY), MONTH(awr.DAY)";

$bind = array(
	':client_id' => $client_id,
	':year_period_start_date' => $year_period_start_date,
	':year_period_end_date' => $year_period_end_date,
);

$result = db::connect('awreports')
	->select($query)
	->binds($bind)
	->execute();

if ($result->row_count() > 0) {
	$row = $result->fetch_all();

	$template->touchBlock("campaign_summary_google_data");

	foreach ($row as $summary_row) {
		foreach ($measurement_array as $label) {
			${$label}[] = array(substr($month_name[$summary_row['month']], 0, 3) . '/' . $summary_row['year'], round((float)$summary_row[$label], 2));

			if (in_array($label, array('clicks', 'impressions'))) {
				$template_data['campaign_summary_' . $label] = number_format($summary_row[$label], 0, ".", ",");
			} else {
				$template_data['campaign_summary_' . $label] = number_format($summary_row[$label], 2, ".", ",");
			}
		}

		if (!$last_month) {
			$last_month = $summary_row['month'];
			$last_year = $summary_row['year'];
			$currency_id = $summary_row['currency_id'];
		}
	}

	// calculates the number of missing months
	if ($result->row_count() < 13) {
		$missing_months_campaign_summary = array();
		$interval = DateInterval::createFromDateString('1 month');
		$period = new DatePeriod(new DateTime($year_period_start_date), $interval, new DateTime($last_year . '-' . $last_month . '-01'));
	}

	// json encoded data to be sent to google chart api
	$measurement_json_data = array();
	foreach ($measurement_array as $label) {
		if ($result->row_count() < 13) {
			$missing_months_campaign_summary = array();

			foreach ($period as $dt) {
				$missing_months_campaign_summary[] = array(substr($month_name[$dt->format("n")], 0, 3) . '/' . $dt->format("Y"), 0);
			}

			${$label} = array_merge($missing_months_campaign_summary, ${$label});
		}

		$measurement_json_data[$label] = array_merge(array(array('Period', $label)), array_reverse(${$label}));
	}

	$currency['currency_id'] = $currency_id != '' ? $currency_id : $_SESSION['members']['currency']['id'];
	$currency_data = get_currency_information($currency['currency_id']);
	$template_data['currency_symbol'] = $currency_data['symbol'];

	if (date("m") == $report_month && date("Y") == $report_year) {
		$date_budget = date("Y-m-d");
	} else {
		$date_budget = date('Y-m-t', mktime(0, 0, 0, $report_month + 1, 0, $report_year));
	}

	$sYra_details = config::get('database.default');
	$sYra = new Database($sYra_details['hostname'], $sYra_details['username'], $sYra_details['password'], $sYra_details['schema']);

	// calculates the budget/day
	$query = "	SELECT budget/:quantity_days AS budget
				FROM sYra_logging.google_advertising_budget gabl
					LEFT JOIN sYra.google_advertising_to_monthly_budget ga2mb ON gabl.product_data_id = ga2mb.monthly_budget_product_data_id
					LEFT JOIN sYra.generic_products_data gpd ON gpd.product_data_id = gabl.product_data_id
				WHERE :date_budget >= start_date
					AND :date_budget <= end_date
					AND IF(gpd.product_id IN (4171, 4172, 4173, 4174, 4175, 4180), ga2mb.monthly_budget_product_data_id = :product_data_id, ga2mb.product_data_id = :product_data_id)";
	
	$bind = array(
		':quantity_days' => $quantity_days,
		':date_budget' => $date_budget,
		':product_data_id' => $product_data_id,
	);

	$row = $db_syssql
		->select($query)
		->binds($bind)
		->execute()
		->fetch();

	$template_data['campaign_summary_budget'] = number_format($row['budget'], 2, ".", ",");
	$template_data['campaign_summary_json_data'] = json_encode($measurement_json_data);
	$contain_google_data = true;
} else {
	$template->hideBlock("campaign_summary_google_data");

	foreach ($measurement_array as $label) {
		$template_data['campaign_summary_' . $label] = 0;
	}

	$template_data['campaign_summary_budget'] = 0;
}

$template->setVariable($template_data);
echo $template->get();
exit();
