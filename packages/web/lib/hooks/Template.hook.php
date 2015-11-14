<?php
/****************************************************
 * FOG Hook: Template
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 4170 $
 *	Last Update:	$LastChangedDate: 2015-10-16 11:49:14 -0400 (Fri, 16 Oct 2015) $
 ***/
// Hook Template
class Template extends Hook {
    public $name = 'Hook Name';
    public $description = 'Hook Description';
    public $author = 'Hook Author';
    public $active = false;
    public function HostData($arguments) {
        $this->log(print_r($arguments, 1));
    }
}
// Hook Event
$HookManager->register('HOST_DATA', array(new Template(), 'HostData'));
