<!-- INCLUDE includes/main_top.tpl -->

<script>
	window.completed_ajax = 0;

	function generate_pdf(action, event) {
		if (completed_ajax !== 5) {
			if(event){
				event.preventDefault();
			}

			return false;
		}

		var images = null;

		if ($('#chart1_div').get(0) && $('#chart2_div').get(0)) {
			if (typeof performance_chart_1 == "undefined" || typeof performance_chart_2 == "undefined") {
				if(event){
					event.preventDefault();
				}

				return false;
			}

			images = JSON.stringify({
				svg1: performance_chart_1.getImageURI(),
				svg2: performance_chart_2.getImageURI()
			});
		}

		var buf;
		var html_parts = {
			page1_part1: $('.page1.part1 .infoBoxes').get(0).outerHTML
		};

		buf = $('.page1.part2 table tbody').clone();
		buf.find('tr').eq(0).remove();
		html_parts.page1_part2 = buf.html();

		buf = $('.page1.part3 table tbody').clone();
		buf.find('tr').eq(0).remove();
		html_parts.page1_part3 = buf.html();

		html_parts.page2_part1 = $('.page2.part1 .blueSubHeading').get(1).outerHTML;

		buf = $('.page2.part1 #advariations table tbody').clone();
		buf.find('tr').eq(0).remove()
		html_parts.page2_part1 = buf.html();

		buf = $('.page3.part1 table tbody').clone();
		buf.find('tr').eq(0).remove()
		html_parts.page3_part1 = buf.html();


		$('<form>', {
			"id": "pdf_report_form",
			"method": "POST",
			"target": "_blank",
			"html": '<input hidden="hidden" name="pdf_action" value=\'' + action + '\'>\n' +
			'<input hidden="hidden" name="images" value=\'' + images + '\'>\n' +
			'<input hidden="hidden" name="html_parts" value=\'' + escapeHtml(JSON.stringify(html_parts)) + '\'>',
			"action": '/members/ad-campaign/make-pdf-report/?domain_name={domain_name}&client_id={client_id}&product_data_id={product_data_id}&report_month={report_month}&report_year={report_year}&year_period_start_date={year_period_start_date}&year_period_end_date={year_period_end_date}'
		}).appendTo(document.body).submit();
	}

	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

</script>
<canvas style="display:none" id="can1"></canvas>
<div class="container cf">

	{side_menu}

	<div class="content">

		<!-- BEGIN product_title_domain_name -->
		<!-- INCLUDE includes/product_title_product_name.tpl -->
		<!-- END product_title_domain_name -->

		<div class="page1 part1">
			<!-- INCLUDE ad_campaign/info_boxes.tpl -->
			<div class="plansTopWrap adReportHeader">
				<h1>Ad Report <span>{report_start_date} - {report_end_date}</span></h1>

				<div class="pdfPrint pdfExclude">
					<a href="#" onclick="generate_pdf('download', event)"
					   class="iconFontDownloadInvoice memberBtnGrey pullRight"></a>
					<a href="#" onclick="generate_pdf('print', event)"
					   class="iconFontPrintInvoice memberBtnGrey pullRight"></a>
				</div>
			</div>
		</div>

		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript">
			google.load('visualization', '1.0', {'packages':['corechart']});

			$(function() {
				$.ajax({
					url: '/members/ajax/ad_campaign/summary/',
					data: 'client_id={client_id}&product_data_id={product_data_id}&report_month={report_month}&report_year={report_year}&year_period_start_date={year_period_start_date}&year_period_end_date={year_period_end_date}',
					type: 'GET',
					cache: false,
					async: true,
					success: function(html) {
						$('#campaign_summary').empty().append(html);
					},
					complete: function(e, status) {
						if (status == "success") {
							completed_ajax++;
						}
					}
				});

				$.ajax({
					url: '/members/ajax/ad_campaign/performance/',
					data: 'client_id={client_id}&year_period_start_date={year_period_start_date}&year_period_end_date={year_period_end_date}',
					type: 'GET',
					cache: false,
					dataType: 'html',
					async: true,
					success: function(html) {
						$('#campaign_performance_history').html(html);
					},
					complete: function(e, status) {
						if (status == "success") {
							completed_ajax++;
						}
						if (typeof drawGraph1 != "undefined" && typeof drawGraph2 != "undefined") {
							drawGraph1();
							drawGraph2();
						}
					}
				});

				$.ajax({
					url: '/members/ajax/ad_campaign/devices/',
					data: 'client_id={client_id}&report_month={report_month}&report_year={report_year}',
					type: 'GET',
					cache: false,
					async: true,
					success: function(html) {
						$('#devices_access').html(html);
					},
					complete: function(e, status) {
						if (status == "success") {
							completed_ajax++;
						}
					}
				});


				$.ajax({
					url: '/members/ajax/ad_campaign/advariations/',
					data: 'client_id={client_id}&report_month={report_month}&report_year={report_year}',
					type: 'GET',
					cache: false,
					async: true,
					success: function(html) {
						$('#advariations').html(html);
					},
					complete: function(e, status) {
						if (status == "success") {
							completed_ajax++;
						}
					}
				});

				$.ajax({
					url: '/members/ajax/ad_campaign/keyword-bidding/',
					data: 'client_id={client_id}&report_month={report_month}&report_year={report_year}',
					type: 'GET',
					cache: false,
					async: true,
					success: function(html) {
						$('#keyword_bidding').html(html);
					},
					complete: function(e, status) {
						if (status == "success") {
							completed_ajax++;
						}
					}
				});
			});
		</script>

		<div class="reportContent">
			<!-- BEGIN load_reports -->

			<div class="page1 part2">
				<h3 class="blueSubHeading">Campaign Summary <span class=" squarePopup pdfExclude"><span
								class=" tooltip_1 iconQuestionMark"></span></span></h3>

				<div id="campaign_summary" class="campaignBlock">
					<div class="infinityLoader"></div>
				</div>
			</div>

			<!-- BEGIN contain_google_data -->
			<div class="page1 part3">
				<h3 class="blueSubHeading">Devices <span class=" squarePopup pdfExclude"><span
								class=" tooltip_111 iconQuestionMark"></span></span></h3>

				<div id="devices_access" class="campaignBlock">
					<div class="infinityLoader"></div>
				</div>
			</div>

			<div class="page2 part1">
				<h3 class="blueSubHeading">Performance History <span class=" squarePopup pdfExclude"><span
								class=" tooltip_2 iconQuestionMark"></span></span></h3>

				<div id="campaign_performance_history" class="campaignBlock">
					<div class="infinityLoader"></div>
				</div>

				<h3 class="blueSubHeading">Search Ads <span class=" squarePopup pdfExclude"><span
								class=" tooltip_4 iconQuestionMark"></span></span></h3>

				<div id="advariations" class="campaignBlock">
					<div class="infinityLoader"></div>
				</div>
			</div>

			<div class="page3 part1">
				<h3 class="blueSubHeading">Keyword Bidding <span class=" squarePopup pdfExclude"><span
								class=" tooltip_5 iconQuestionMark"></span></span> <span
							style="text-align: right; font-size: 12px; font-weight: normal;">(top 15 keywords showing)</span>
				</h3>

				<div id="keyword_bidding" class="campaignBlock">
					<div class="infinityLoader"></div>
				</div>
			</div>
			<!-- END contain_google_data -->
			<!-- END load_reports -->

			<!-- BEGIN no_google_data -->
			<p class="noProdAvailable">Report data for this month has not been calculated yet</p>
			<!-- END no_google_data -->

		</div>

	</div>

</div>

<div id="inline_tooltip_1" style="display:none;" class="globalTip"><p><strong>Campaign Summary</strong></p>

	<p>This is your Search Ad overall campaign summary, how your website is performing.</p></div>
<div id="inline_tooltip_2" style="display:none;" class="globalTip"><p><strong>Performance History</strong></p>

	<p>This is the overal performance for your Search Ad campaign.</p></div>
<div id="inline_tooltip_4" style="display:none;" class="globalTip"><p><strong>Search Ads</strong></p>

	<p>Your search Ad variations for each Adgroup are displayed below. Your Ad Manager will regularly monitor and adjust
		these Ads for the best click through rate (CTR) possible.</p></div>
<div id="inline_tooltip_5" style="display:none;" class="globalTip"><p><strong>Keyword Bidding</strong></p>

	<p>These are the keywords currently being bid on based on their relevance to your website.</p></div>

<div id="inline_tooltip_111" style="display:none;" class="globalTip"><p><strong>Devices</strong></p>

	<p>This shows the devices used for your vistors, how they find your Search Ad.</p></div>

<!-- INCLUDE includes/main_bottom.tpl -->
