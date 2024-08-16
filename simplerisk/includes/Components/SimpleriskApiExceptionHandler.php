<?php

use Leaf\Exception\Handler\Handler;
use Leaf\Exceptions\Formatter;

class SimpleriskApiExceptionHandler extends Handler
{

    protected $handler = null;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct($handler = null)
    {
        $this->handler = is_callable($handler) ? $handler : function (\Throwable $e) {
            global $escaper;
            error_log(Formatter::formatExceptionPlain($this->getInspector()));
            
            // Although in certain cases this message gets escaped again,
            // I'd prefer to have a message that's double-escaped than one that gets through without escaping
            response()->json(create_json_response_array(500, $escaper->escapeHtml($e->getMessage())), 500, false);
        };
    }

    /**
     * The function that's run by Leaf's exception handling logic   
     * 
     * @return int|null
     *
     * @throws \Exception
     */
    public function handle() {
        call_user_func($this->handler, $this->getException());
        return Handler::LAST_HANDLER;
    }
}
    
?>