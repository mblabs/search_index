google.load('visualization', '1.0', {'packages':['corechart']});
google.setOnLoadCallback(drawChart);

function drawChart() {

	var data = new google.visualization.DataTable();
	data.addColumn('number', 'Rank');
	data.addColumn('number', 'Frequency');
	
	var rows = [];
	jQuery('table tbody tr').each(function(i) {
		var rank = parseInt(jQuery(this).find('.rank').text());
		var count = parseInt(jQuery(this).find('.count').text());
		rows.push([rank, count]);
	});
	data.addRows(rows);

	var chart = new google.visualization.ScatterChart(document.getElementById('long-tail'));
	chart.draw(
		data,
		{
			width: 760,
			height: 300,
			curveType: 'none',
			fontSize: 11,
			fontName: 'Lucida Grande',
			hAxis: {
				gridlineColor: '#eee',
				baselineColor: '#aaa'
			},
			vAxis: {
				baselineColor: '#aaa',
				gridlineColor: '#eee',
			},
			chartArea: {
				top: 10,
				left: 30
			},
			legend: 'none',
			pointSize: 0,
			lineWidth: 1,
		}
	);

};

jQuery(document).ready(function() {
	
	
	
});