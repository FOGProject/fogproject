<?php
require_once('../commons/base.inc.php');
$report = unserialize($_SESSION['foglastreport']);
if (!($report instanceof ReportMaker)) $report = $FOGCore->getClass(ReportMaker);
$report->outputReport();
