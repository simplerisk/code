<?php

/* Customized phpHighchart for simplerisk in PHP 8
   */

$jsFiles = array(
    'jQuery' => array(
        'name' => 'jquery-2.1.3.min.js',
        'path' => '//code.jquery.com/'
    ),

    'mootools' => array(
        'name' => 'mootools-yui-compressed.js',
        'path' => '//ajax.googleapis.com/ajax/libs/mootools/1.4.5/'
    ),

    'prototype' => array(
        'name' => 'prototype.js',
        'path' => '//ajax.googleapis.com/ajax/libs/prototype/1.7.0.0/'
    ),

    'highcharts' => array(
        'name' => 'highcharts.js',
        'path' => '//code.highcharts.com/'
    ),

    'highchartsMootoolsAdapter' => array(
        'name' => 'mootools-adapter.js',
        'path' => '//code.highcharts.com/adapters/'
    ),

    'highchartsPrototypeAdapter' => array(
        'name' => 'prototype-adapter.js',
        'path' => '//code.highcharts.com/adapters/'
    ),

    'highstock' => array(
        'name' => 'highstock.js',
        'path' => '//code.highcharts.com/stock/'
    ),

    'highstockMootoolsAdapter' => array(
        'name' => 'mootools-adapter.js',
        'path' => '//code.highcharts.com/stock/adapters/'
    ),

    'highstockPrototypeAdapter' => array(
        'name' => 'prototype-adapter.js',
        'path' => '//code.highcharts.com/stock/adapters/'
    ),

    'highmaps' => array(
        'name' => 'highmaps.js',
        'path' => '//code.highcharts.com/maps/'
    ),

    //Extra scripts used by Highcharts 3.0 charts
    'extra' => array(
        'highcharts-more' => array(
            'name' => 'highcharts-more.js',
            'path' => '//code.highcharts.com/'
        ),
        'exporting' => array(
            'name' => 'exporting.js',
            'path' => '//code.highcharts.com/modules/'
        ),
    )
);


class simpleriskHighchart
{
    //The chart type.
    //A regullar higchart
    const HIGHCHART = 0;
    //A highstock chart
    const HIGHSTOCK = 1;
    // A Highchart map
    const HIGHMAPS = 2;

    //The js engine to use
    const ENGINE_JQUERY = 10;
    const ENGINE_MOOTOOLS = 11;
    const ENGINE_PROTOTYPE = 12;

    /**
     * The chart options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * The chart type.
     * Either self::HIGHCHART or self::HIGHSTOCK
     *
     * @var int
     */
    protected $_chartType;

    /**
     * The javascript library to use.
     * One of ENGINE_JQUERY, ENGINE_MOOTOOLS or ENGINE_PROTOTYPE
     *
     * @var int
     */
    protected $_jsEngine;

    /**
     * Array with keys from extra scripts to be included
     *
     * @var array
     */
    protected $_extraScripts = array();

    /**
     * Any configurations to use instead of the default ones
     *
     * @var array An array with same structure as the config.php file
     */
    protected $_confs = array();

    /**
     * Clone Highchart object
     */
    public function __clone()
    {
        foreach ($this->_options as $key => $value)
        {
            $this->_options[$key] = clone $value;
        }
    }

    /**
     * The Highchart constructor
     *
     * @param int $chartType The chart type (Either self::HIGHCHART or self::HIGHSTOCK)
     * @param int $jsEngine  The javascript library to use
     *                       (One of ENGINE_JQUERY, ENGINE_MOOTOOLS or ENGINE_PROTOTYPE)
     */
    public function __construct($chartType = self::HIGHCHART, $jsEngine = self::ENGINE_JQUERY)
    {
        $this->_chartType = is_null($chartType) ? self::HIGHCHART : $chartType;
        $this->_jsEngine = is_null($jsEngine) ? self::ENGINE_JQUERY : $jsEngine;
        //Load default configurations
        $this->setConfigurations();
    }

    /**
     * Override default configuration values with the ones provided.
     * The provided array should have the same structure as the config.php file.
     * @param array $configurations The new configuration values
     */
    public function setConfigurations($configurations = array())
    {
        global $jsFiles;
        $this->_confs = array_replace_recursive($jsFiles, $configurations);
    }

    /**
     * Render the chart options and returns the javascript that
     * represents them
     *
     * @return string The javascript code
     */
    public function renderOptions()
    {
        return HighchartOptionRenderer::render($this->_options);
    }

    /**
     * Render the chart and returns the javascript that
     * must be printed to the page to create the chart
     *
     * @param string $varName The javascript chart variable name
     * @param string $callback The function callback to pass
     *                         to the Highcharts.Chart method
     * @param boolean $withScriptTag It renders the javascript wrapped
     *                               in html script tags
     *
     * @return string The javascript code
     */
    public function render($varName = null, $callback = null, $withScriptTag = false)
    {
        $result = '';
        if (!is_null($varName)) {
            $result = "$varName = ";
        }

        $result .= 'new Highcharts.';
        if ($this->_chartType === self::HIGHCHART) {
            $result .= 'Chart(';
        } elseif ($this->_chartType === self::HIGHMAPS) {
            $result .= 'Map(';
        } else {
            $result .= 'StockChart(';
        }

        $result .= $this->renderOptions();
        $result .= is_null($callback) ? '' : ", $callback";
        $result .= ');';

        if ($withScriptTag) {
            $result = '<script type="text/javascript">' . $result . '</script>';
        }

        return $result;
    }

    /**
     * Finds the javascript files that need to be included on the page, based
     * on the chart type and js engine.
     * Uses the conf.php file to build the files path
     *
     * @return array The javascript files path
     */
    public function getScripts()
    {
        $scripts = array();
        switch ($this->_jsEngine) {
            case self::ENGINE_JQUERY:
                $scripts[] = $this->_confs['jQuery']['path'] . $this->_confs['jQuery']['name'];
                break;

            case self::ENGINE_MOOTOOLS:
                $scripts[] = $this->_confs['mootools']['path'] . $this->_confs['mootools']['name'];
                if ($this->_chartType === self::HIGHCHART) {
                    $scripts[] = $this->_confs['highchartsMootoolsAdapter']['path'] . $this->_confs['highchartsMootoolsAdapter']['name'];
                } else {
                    $scripts[] = $this->_confs['highstockMootoolsAdapter']['path'] . $this->_confs['highstockMootoolsAdapter']['name'];
                }
                break;

            case self::ENGINE_PROTOTYPE:
                $scripts[] = $this->_confs['prototype']['path'] . $this->_confs['prototype']['name'];
                if ($this->_chartType === self::HIGHCHART) {
                    $scripts[] = $this->_confs['highchartsPrototypeAdapter']['path'] . $this->_confs['highchartsPrototypeAdapter']['name'];
                } else {
                    $scripts[] = $this->_confs['highstockPrototypeAdapter']['path'] . $this->_confs['highstockPrototypeAdapter']['name'];
                }
                break;

        }

        switch ($this->_chartType) {
            case self::HIGHCHART:
                $scripts[] = $this->_confs['highcharts']['path'] . $this->_confs['highcharts']['name'];
                break;

            case self::HIGHSTOCK:
                $scripts[] = $this->_confs['highstock']['path'] . $this->_confs['highstock']['name'];
                break;

            case self::HIGHMAPS:
                $scripts[] = $this->_confs['highmaps']['path'] . $this->_confs['highmaps']['name'];
                break;
        }

        //Include scripts with keys given to be included via includeExtraScripts
        if (!empty($this->_extraScripts)) {
            foreach ($this->_extraScripts as $key) {
                $scripts[] = $this->_confs['extra'][$key]['path'] . $this->_confs['extra'][$key]['name'];
            }
        }

        return $scripts;
    }

    /**
     * Prints javascript script tags for all scripts that need to be included on page
     *
     * @param boolean $return if true it returns the scripts rather then echoing them
     */
    public function printScripts($return = false)
    {
        $scripts = '';
        foreach ($this->getScripts() as $script) {
            $scripts .= '<script type="text/javascript" src="' . $script . '"></script>';
        }

        if ($return) {
            return $scripts;
        }
        else {
            echo $scripts;
        }
    }

    /**
     * Manually adds an extra script to the extras
     *
     * @param string $key      key for the script in extra array
     * @param string $filepath path for the script file
     * @param string $filename filename for the script
     */
    public function addExtraScript($key, $filepath, $filename)
    {
        $this->_confs['extra'][$key] = array('name' => $filename, 'path' => $filepath);
    }

    /**
     * Signals which extra scripts are to be included given its keys
     *
     * @param array $keys extra scripts keys to be included
     */
    public function includeExtraScripts(array $keys = array())
    {
        $this->_extraScripts = empty($keys) ? array_keys($this->_confs['extra']) : $keys;
    }

    /**
     * Global options that don't apply to each chart like lang and global
     * must be set using the Highcharts.setOptions javascript method.
     * This method receives a set of HighchartOption and returns the
     * javascript string needed to set those options globally
     *
     * @param HighchartOption The options to create
     *
     * @return string The javascript needed to set the global options
     */
    public static function setOptions($options)
    {
        //TODO: Check encoding errors
        $option = json_encode($options->getValue());
        return "Highcharts.setOptions($option);";
    }

    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->_options[$offset] = new HighchartOption($value);
    }

    public function offsetExists($offset)
    {
        return isset($this->_options[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->_options[$offset]);
    }

    public function offsetGet($offset)
    {
        if (!isset($this->_options[$offset])) {
            $this->_options[$offset] = new HighchartOption();
        }
        return $this->_options[$offset];
    }
}

class HighchartOption
{
    /**
     * An array of HighchartOptions
     *
     * @var array
     */
    private $_childs = array();

    /**
     * The option value
     *
     * @var mixed
     */
    private $_value;

    /**
     * Clone HighchartOption object
     */
    public function __clone()
    {
        foreach ($this->_childs as $key => $value)
        {
            $this->_childs[$key] = clone $value;
        }
    }

    /**
     * The HighchartOption constructor
     *
     * @param mixed $value The option value
     */
    public function __construct($value = null)
    {
        if (is_string($value)) {
            //Avoid json-encode errors latter on
            if(function_exists('iconv')){
                $this->_value = iconv(
                        mb_detect_encoding($value),
                        "UTF-8",
                        $value
                );
            } else {// fallback for servers that does not have iconv  
                $this->_value = mb_convert_encoding($value, "UTF-8", mb_detect_encoding($value));
            }
        } else if (!is_array($value)) {
            $this->_value = $value;
        } else {
            foreach($value as $key => $val) {
                $this->offsetSet($key, $val);
            }
        }
    }

    /**
     * Returns the value of the current option
     *
     * @return mixed The option value
     */
    public function getValue()
    {
        if (isset($this->_value)) {
            //This is a final option
            return $this->_value;
        } elseif (!empty($this->_childs)) {
            //The option value is an array
            $result = array();
            foreach ($this->_childs as $key => $value) {
                $result[$key] = $value->getValue();
            }
            return $result;
        }
        return null;
    }

    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_childs[] = new self($value);
        } else {
            $this->_childs[$offset] = new self($value);
        }
        //If the option has at least one child, then it won't
        //have a final value
        unset($this->_value);
    }

    public function offsetExists($offset)
    {
        return isset($this->_childs[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->_childs[$offset]);
    }

    public function offsetGet($offset)
    {
        //Unset the value, because we will always
        //have at least one child at the end of
        //this method
        unset($this->_value);
        if (is_null($offset)) {
            $this->_childs[] = new self();
            return end($this->_childs);
        }
        if (!isset($this->_childs[$offset])) {
            $this->_childs[$offset] = new self();
        }
        return $this->_childs[$offset];
    }
}

class HighchartOptionRenderer
{
    /**
     * Render the options and returns the javascript that
     * represents them
     *
     * @return string The javascript code
     */
    public static function render($options)
    {
        $jsExpressions = array();
        //Replace any js expression with random strings so we can switch
        //them back after json_encode the options
        $options = static::_replaceJsExpr($options, $jsExpressions);

        //TODO: Check for encoding errors
        $result = json_encode($options);

        //Replace any js expression on the json_encoded string
        foreach ($jsExpressions as $key => $expr) {
            $result = str_replace('"' . $key . '"', $expr, $result);
        }
        return $result;
    }

    /**
     * Replaces any HighchartJsExpr for an id, and save the
     * js expression on the jsExpressions array
     * Based on Zend_Json
     *
     * @param mixed $data           The data to analyze
     * @param array &$jsExpressions The array that will hold
     *                              information about the replaced
     *                              js expressions
     */
    private static function _replaceJsExpr($data, &$jsExpressions)
    {
        if (!is_array($data) &&
            !is_object($data)) {
            return $data;
        }

        if (is_object($data)) {
            if ($data instanceof \stdClass) {
                return $data;
            } elseif (!$data instanceof HighchartJsExpr) {
                $data = $data->getValue();
            }
        }

        if ($data instanceof HighchartJsExpr) {
            $magicKey = "____" . count($jsExpressions) . "_" . count($jsExpressions);
            $jsExpressions[$magicKey] = $data->getExpression();
            return $magicKey;
        }
        if (!is_null($data)){
            foreach ($data as $key => $value) {
                $data[$key] = static::_replaceJsExpr($value, $jsExpressions);
            }
        }
        return $data;
    }
}

class HighchartJsExpr
{
    /**
     * The javascript expression
     *
     * @var string
     */
    private $_expression;

    /**
     * The HighchartJsExpr constructor
     *
     * @param string $expression The javascript expression
     */
    public function __construct($expression)
    {
        $this->_expression = iconv(
            mb_detect_encoding($expression),
            "UTF-8",
            $expression
        );
    }

    /**
     * Returns the javascript expression
     *
     * @return string The javascript expression
     */
    public function getExpression()
    {
        return $this->_expression;
    }
}

?>
