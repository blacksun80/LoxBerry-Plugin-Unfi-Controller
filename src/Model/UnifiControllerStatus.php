<?php

namespace LoxBerryUnifiPlugin\Model;

/**
 * Unifi controller status model as defined in the unifi api.
 */
class UnifiControllerStatus
{
    public $version;

    function __construct($version) {
        $this->version = $version;
    }
}
