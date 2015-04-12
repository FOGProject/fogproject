<?php
require_once('../commons/base.inc.php');
$report = isset($_REQUEST['export']) ? $FOGCore->getClass('ReportMaker') : unserialize($_SESSION['foglastreport']);
$report->outputReport();
