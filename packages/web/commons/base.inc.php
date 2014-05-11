<?php
	/* This file just stores the heading information for including *
	 * In the FOG System.  This should minimize code lines.        *
	 *                                                             */
	require_once('system.php');
	require_once(BASEPATH . '/commons/config.php');
	require_once(BASEPATH . '/commons/init.php');
	require_once(BASEPATH . '/commons/init.database.php');
	require_once(BASEPATH . '/commons/text.php');
	$HookManager = new HookManager();
	$FOGPageManager = new FOGPageManager();
