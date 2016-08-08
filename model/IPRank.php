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

    public function set(array $data)
    {
        if (is_array($data)) {
            $this->name = $data['name'];
            $this->time = $data['time'];
        }
        if (is_array($data['rank'])) {
            $this->rank = $data['rank'];
        } else {
            $scores = (array)($data['rank']);
            $this->rank = array();
            foreach ($scores as $key => $score) {
                $this->rank[$key] = (double)$scores;
            }
        }
    }
}