<?php

define('TEST_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'test');

uses()
    ->afterEach(function () {
        $dirs = [
            __DIR__ . DIRECTORY_SEPARATOR . 'test', 
            __DIR__ . DIRECTORY_SEPARATOR . 'test-new', 
        ];

        foreach($dirs as $dir){
            if(is_dir($dir)){
                $objects = scandir($dir);
                foreach ($objects as $object) { 
                    if ($object != "." && $object != "..") { 
                        if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir."/".$object))
                            rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                        else
                            unlink($dir . DIRECTORY_SEPARATOR . $object); 
                    } 
                }

                rmdir($dir); 
            }
        }
    })
    ->in(__DIR__);