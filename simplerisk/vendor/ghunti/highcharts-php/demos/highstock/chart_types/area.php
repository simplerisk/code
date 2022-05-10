<?php
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;

$chart = new Highchart(Highchart::HIGHSTOCK);

$chart->chart->renderTo = "container";
$chart->rangeSelector->selected = 1;
$chart->title->text = "AAPL Stock Price";
$chart->series[0]->name = "AAPL Stock Price";
$chart->series[0]->data = new HighchartJsExpr("data");
$chart->series[0]->type = "area";
$chart->series[0]->threshold = null;
$chart->series[0]->tooltip->valueDecimals = 2;
$chart->series[0]->fillColor->linearGradient->x1 = 0;
$chart->series[0]->fillColor->linearGradient->y1 = 0;
$chart->series[0]->fillColor->linearGradient->x2 = 0;
$chart->series[0]->fillColor->linearGradient->y2 = 1;

$chart->series[0]->fillColor->stops = array(
    array(
        0,
        new HighchartJsExpr("Highcharts.getOptions().colors[0]")
    ),
    array(
        1,
        "rgba(0,0,0,0)"
    )
);

?>

<html>
    <head>
        <title>Area</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <?php $chart->printScripts(); ?>
    </head>
    <body>
        <div id="container"></div>
        <script type="text/javascript">
            $.getJSON('http://www.highcharts.com/samples/data/jsonp.php?filename=aapl-c.json&callback=?', function(data) {
                <?php echo $chart->render("chart"); ?>;
            });
        </script>
    </body>
</html>