<?php
/** A protected directory writer was refused; never permission for a replay. */
namespace FOG\Agent;

class DirectoryFenceRefusal extends \RuntimeException
{
    public function __construct($reason)
    {
        parent::__construct($reason, 409);
    }
}
