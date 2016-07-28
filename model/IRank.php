<?php

/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/22/16
 * Time: 4:27 PM
 */
interface IRank
{
    public function name();

    public function rank();

    public function score($keys);

}