<?php

/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/28/16
 * Time: 11:18 AM
 */
class status
{
    public $status = '';
    const OK = 'ok';
    const ERROR = 'error';
    const FAILED = 'failed';
    public function __construct($st)
    {
        $this->status=$st;
    }
}