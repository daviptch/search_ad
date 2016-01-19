<?php
require_once('../includes/config.php');
require_once(LOCAL_INCLUDES_DIR . 'application_top.php');
require_once(COMMON_CLASSES_DIR . 'helpers/html.php');

if ($_SESSION['members']['active_member']->locale != 'au') {
	redirect('/members/');
}

handle_template_blocks('tipped_full_block', 'tipped_inline_create');

// load reports template
$template->loadTemplatefile('ad_campaign/reports.tpl');

$product_data_id = sanitize_int($_GET['product_data_id']);
$report_month = sanitize_int($_GET['report_month']);
$report_year = sanitize_int($_GET['report_year']);

if (!($product_data_id && $report_month && $report_year)) {
	redirect('/members/ad-campaign/');
}

$contain_google_data = false;
$year_period_start_date = date('Y-m-d', mktime(0, 0, 0, $report_month + 1, 1, $report_year - 1));
$year_period_end_date = date('Y-m-d', mktime(0, 0, 0, $report_month + 1, 0, $report_year));

// ad report date range
$current_date = date('Y-m-d');
$last_day_report_month = date('Y-m-d', mktime(0, 0, 0, $report_month + 1, 0, $report_year));
$template_data['report_start_date'] = date('F jS Y', mktime(0, 0, 0, $report_month, 1, $report_year));

if ($current_date > $last_day_report_month) {
	// past months
	$template_data['report_end_date'] = date('F tS Y', mktime(0, 0, 0, $report_month + 1, 0, $report_year));
} else if (date('d') > 1) {
	// current month
	$template_data['report_end_date'] = date('F jS Y', strtotime($current_date . ' - 1 day'));
} else {
	// current month day 1
	$template_data['report_end_date'] = date('F jS Y');
}

$report_end_date = date('Y-m-d', strtotime($template_data['report_end_date']));

// due to search ads restructuring and keeping the old product yet we need to identify which is to show the info properly
$query = "	SELECT product_id
			      FROM sYra.generic_products_data gpd
			      WHERE gpd.product_data_id = {$product_data_id}";

$db_syssql = db::connect('syssql');

$product_row = $db_syssql
  ->select($query)
  ->binds(array(':product_data_id' => $product_data_id,))
  ->execute()
  ->fetch();

$new_search_ads = in_array($product_row['product_id'], explode(',', $product_information['new_product_id'])) ? true : false;

if ($new_search_ads) {
	$query_google_advertising_join = "ga.product_data_id = ga2mb.monthly_budget_product_data_id";
} else {
  $query_google_advertising_join = "ga.product_data_id = ga2mb.product_data_id";
}

// pull data from sYra
$query = "	SELECT ga.business_name, client_id, ga.ad_links, gpd.product_status, gpd.currency_id,
				gabl.budget AS monthly_budget,
				ga2mb.current_monthly_budget AS current_monthly_budget,
				gapc.budget_credit AS balance
			FROM sYra.google_advertising ga
				LEFT JOIN sYra.google_advertising_to_monthly_budget ga2mb ON {$query_google_advertising_join}
				LEFT JOIN sYra.generic_products_data gpd ON ga2mb.monthly_budget_product_data_id = gpd.product_data_id
				LEFT JOIN sYra.google_advertising_product_credit gapc ON gapc.product_data_id = ga2mb.monthly_budget_product_data_id
				LEFT JOIN (
					SELECT product_data_id, budget
					FROM sYra_logging.google_advertising_budget
					WHERE :report_end_date > start_date
						AND :report_end_date <= end_date
				) gabl ON gabl.product_data_id = ga2mb.monthly_budget_product_data_id
			WHERE ga.product_data_id = :product_data_id";

$bind = array(
	':product_data_id' => $product_data_id,
	':report_end_date' => $report_end_date,
);

$original_row = $db_syssql
  ->select($query)
  ->binds($bind)
  ->execute()
  ->fetch();

// pull data from AWReports
$query = "	SELECT account_descriptive_name,
				SUM(cost) AS monthly_spend,
				SUM(clicks) AS total_clicks,
				SUM(impressions) AS total_impressions
			FROM AWReports.AW_ReportAd awr
			WHERE awr.account_id = :client_id
				AND MONTH(day) = :report_month
				AND YEAR(day) = :report_year
				AND awr.DEVICE IS NOT NULL";

$bind = array(
	':client_id' => $original_row['client_id'],
	':report_month' => $report_month,
	':report_year' => $report_year,
);

try {
	$result = db::connect('awreports')
		->select($query)
		->binds($bind)
		->execute();
} catch (DatabaseException $e) {
	$_SESSION['members']['temp']['error']['check'][] = array('', 'Reports currently unavailable');
	redirect('/members/ad-campaign/');
}

if ($result->row_count() > 0) {
	$row = $result->fetch();

	$contain_google_data = true;
}

$original_row = array_merge($original_row, $row);

$original_row['currency_id'] = !empty($original_row['currency_id']) ? $original_row['currency_id'] : $_SESSION['members']['currency']['id'];
$currency_data = get_currency_information($original_row['currency_id']);
$client_id = $original_row['client_id'];
$account_descriptive_name = $original_row['account_descriptive_name'];

$template_data['product_data_id'] = $product_data_id;
$template_data['year_period_start_date'] = $year_period_start_date;
$template_data['year_period_end_date'] = $year_period_end_date;
$template_data['client_id'] = $client_id;
$template_data['report_month'] = $report_month;
$template_data['report_year'] = $report_year;
$template_data['base_product_link'] = "../details/?id=$product_data_id";

// header information
$template_data['domain_name'] = $original_row['ad_links'];
$template_data['ad_links'] = add_http($original_row['ad_links']);
$template_data['monthly_budget'] = ($original_row['monthly_budget'] > 0) ? number_format($original_row['monthly_budget'], 2, '.', ',') : (($original_row['current_monthly_budget'] > 0) ? number_format($original_row['current_monthly_budget'], 2, '.', ',') : '0.00');
$template_data['monthly_balance'] = (date('Y-m') == date('Y-m', mktime(0,0,0,$report_month,1,$report_year))) ? number_format($original_row['balance'], 2, '.', ',') : '0.00';
$template_data['currency_symbol'] = $currency_data['symbol'];
$template_data['monthly_spend'] = $original_row['monthly_spend'] ? number_format($original_row['monthly_spend'], 2, '.', ',') : '0.00';
$template_data['monthly_clicks'] = $original_row['total_clicks'] ? number_format($original_row['total_clicks'], 0, '.', ',') : 0;
$template_data['monthly_impressions'] = $original_row['total_impressions'] ? number_format($original_row['total_impressions'], 0, '.', ',') : 0;
$template_data['domain_name_title'] = base::truncate_middle_text($original_row['ad_links'], 37);

if (strlen($template_data['domain_name']) > 35) {
	$template_data['domain_icon_title'] = $template_data['domain_name'];
}

$template_data['product_icon_class'] = 'iconFontGoogleAdvertising';

// data for info_boxes.tpl
$template_data['clicks'] = $template_data['monthly_clicks'];
$template_data['monthly_budget_currency_symbol'] = $currency_data['symbol'];
$template_data['monthly_spend_currency_symbol'] = $currency_data['symbol'];
$template_data['current_monthly_budget'] = $original_row['current_monthly_budget'];

if ($contain_google_data) {
	$template->touchBlock('load_reports');
	$template->touchBlock('contain_google_data');
} else {
	$template->touchBlock('no_google_data');
	$template->hideBlock('contain_google_data');
}

$template->touchBlock('product_title_domain_name');
$template->touchBlock('title_domain_info');
$template->touchBlock('title_whois_popup');
$template->touchBlock('domain_icon_title');

require_once(LOCAL_INCLUDES_DIR . 'application_bottom.php');
