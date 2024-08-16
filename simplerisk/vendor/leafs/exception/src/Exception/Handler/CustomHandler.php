<?php

/**
 * Whoops - php errors for cool kids
 * @author Filipe Dobreira <http://github.com/filp>
 */

namespace Leaf\Exception\Handler;

use Leaf\Exception\General;
use Leaf\Exception\Util\TemplateHelper;

class CustomHandler extends Handler
{
    /**
     * @var TemplateHelper
     */
    protected $templateHelper;

    protected $handler = null;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct($handler = null)
    {
        $this->handler = is_callable($handler) ? $handler : function ($e) {
            echo General::defaultError($e);
        };
    }

    /**
     * @return int|null
     *
     * @throws \Exception
     */
    public function handle()
    {
        $inspector = $this->getInspector();

        call_user_func($this->handler, $inspector->getException());

        return Handler::QUIT;
    }
}
