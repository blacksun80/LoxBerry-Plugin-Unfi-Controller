<?php

namespace LoxBerryUnifiPlugin\Model;

/**
 * Unifi controller status model as defined in the unifi api.
 */
class UnifiControllerStatus
{
    public $version;
    public $containerVersion;
    public $status;

    function __construct($version,$containerVersion,$status) {
        $this->version = $version;
        $this->containerVersion=$containerVersion;
        $this->status=$status;
    }
}
