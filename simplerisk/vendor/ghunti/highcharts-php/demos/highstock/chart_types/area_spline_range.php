<?php
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;

$chart = new Highchart(Highchart::HIGHSTOCK);
$chart->includeExtraScripts();

$chart->chart->type = 'areasplinerange';
$chart->rangeSelector->selected = 2;
$chart->title->text = "Temperature variation by day";
$chart->tooltip->valueSuffix = 'ºC';
$chart->series[] = array(
    'name' => 'Temperatures',
    'data' => new HighchartJsExpr('data')
);
?>

<html>
    <head>
        <title>Area spline range</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <?php $chart->printScripts(); ?>
    </head>
    <body>
        <div id="container"></div>
        <script type="text/javascript">
            $.getJSON('http://www.highcharts.com/samples/data/jsonp.php?filename=range.json&callback=?', function(data) {
                $('#container').highcharts('StockChart', <?php echo $chart->renderOptions(); ?>)});
        </script>
    </body>
</html>