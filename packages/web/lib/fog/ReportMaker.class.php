<?php
class ReportMaker extends FOGBase {
    private $strHTML, $strCSV, $strLine, $filename;
    public $types = array(
        'html' => 0,
        'csv' => 1,
        'pdf' => 2,
        'sql' => 3,
        'user' => 4,
        'host' => 4,
        'group' => 4,
        'image' => 4,
        'snapin' => 4,
        'printer' => 4,
    );
    public function appendHTML($html) {
        $this->strHTML[] = $html;
        return $this;
    }
    public function addCSVCell($item) {
        $this->strCSV[] = $this->DB->sanitize($item);
        return $this;
    }
    public function endCSVLine() {
        $this->strLine[] = '"'.implode('","',$this->strCSV).'"';
        unset($this->strCSV);
        return $this;
    }
    public function setFileName($filename) {
        $this->filename = $filename;
        return $this;
    }
    public function outputReport($intType = 0) {
        if (!isset($_REQUEST['export'])) $this->setFileName($_REQUEST['filename']);
        if ($intType !== false) $intType = (isset($_REQUEST['export']) ? 3 : $this->types[$_REQUEST['type']]);
        else $intType = 0;
        if ($intType == 0) echo implode("\n",(array)$this->strHTML);
        else if ($intType == 1) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$this->filename.'.csv"');
            echo implode($this->strLine,"\n");
        } else if ($intType == 2) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.$this->filename.'.pdf"');
            $proc = proc_open("htmldoc --links --header . --linkstyle plain --numbered --size letter --no-localfiles -t pdf14 --quiet --jpeg --webpage --size letter --left 0.25in --right 0.25in --top 0.25in --bottom 0.25in --header ... --footer ... -", array(0 => array("pipe", "r"), 1 => array("pipe", "w")), $pipes);
            fwrite($pipes[0], '<html><body>'.implode($this->strHTML,"\n")."</body></html>" );
            fclose($pipes[0]);
            fpassthru($pipes[1]);
            $status = proc_close($proc);
        } else if ($intType == 3) {
            $SchemaSave = $this->getClass('Schema');
            $SchemaSave->send_file($SchemaSave->export_db());
        } else if ($intType == 4) {
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=\"{$_REQUEST['type']}_export.csv\"");
            echo implode("\n",$this->strLine);
        }
    }
}
