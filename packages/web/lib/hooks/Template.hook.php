<?php
/****************************************************
 * FOG Hook: Template
 *	Author:		Blackout
 *	Created:	8:57 AM 31/08/2011
 *	Revision:	$Revision: 3563 $
 *	Last Update:	$LastChangedDate: 2015-06-16 12:02:44 -0400 (Tue, 16 Jun 2015) $
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
