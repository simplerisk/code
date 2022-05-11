<?php
use Ghunti\HighchartsPHP\Highchart;
use Ghunti\HighchartsPHP\HighchartJsExpr;

$chart = new Highchart(Highchart::HIGHSTOCK);

$chart->chart->renderTo = "container";
$chart->rangeSelector->selected = 1;
$chart->title->text = "USD to EUR exchange rate";
$chart->yAxis->title->text = "Exchange rate";

$chart->yAxis->plotLines[] = array(
    'value' => 0.6738,
    'color' => "green",
    'dashStyle' => "shortdash",
    'width' => 2,
    'label' => array(
        'text' => "Last quarter minimum"
    )
);

$chart->yAxis->plotLines[] = array(
    'value' => 0.7419,
    'color' => "red",
    'dashStyle' => "shortdash",
    'width' => 2,
    'label' => array(
        'text' => "Last quarter maximum"
    )
);

$chart->series[] = array(
    'name' => "USD to EUR",
    'data' => new HighchartJsExpr("data"),
    'tooltip' => array(
        'valueDecimals' => 4
    )
);
?>

<html>
    <head>
        <title>Plot lines on Y axis</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <?php $chart->printScripts(); ?>
    </head>
    <body>
        <div id="container"></div>
        <script type="text/javascript">
            $.getJSON('http://www.highcharts.com/samples/data/jsonp.php?filename=usdeur.json&callback=?', function(data) {
                <?php echo $chart->render("chart"); ?>;
            });
        </script>
    </body>
</html>