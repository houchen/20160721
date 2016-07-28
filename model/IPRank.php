<?php

/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/21/16
 * Time: 2:42 PM
 */
require_once('IRank.php');

class IPRank
{
    public $name = null;
    public $time = null;
    public $rank;

    public function __construct($data = null)
    {
        if ($data != null) {
            $this->set($data);
        }
    }

    public function set($data)
    {
        foreach ($data as $key => $item) {
            $this->{$key} = $item;
        }
    }
}