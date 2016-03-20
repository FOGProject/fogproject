<?php
class ReportMaker {
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
        'sqldump' => 5,
    );
    public function appendHTML($html) {
        $this->strHTML[] = $html;
        return $this;
    }
    public function addCSVCell($item) {
        $this->strCSV[] = stripslashes(html_entity_decode(htmlentities($item,ENT_QUOTES,'utf-8'),ENT_QUOTES,'utf-8'));
        return $this;
    }
    public function endCSVLine() {
        $this->strLine[] = sprintf('"%s"%s',implode('","',$this->strCSV),"\n");
        unset($this->strCSV);
        return $this;
    }
    public function setFileName($filename) {
        $this->filename = $filename;
        return $this;
    }
    public function outputReport($intType = 0) {
        $keys = array_keys($this->types);
        $type = isset($_REQUEST['type']) ? $type = htmlentities($_REQUEST['type'],ENT_QUOTES,'utf-8') : $keys[$intType];
        if (!in_array($type,$keys)) die(_('Invalid type'));
        $file = basename(trim(htmlentities($_REQUEST['file'],ENT_QUOTES,'utf-8')));
        if (!isset($_REQUEST['export'])) $this->setFileName($file);
        $intType = ($intType !== false ? (isset($_REQUEST['export']) ? 3 : $this->types[$type]) : 0);
        switch ((int) $intType) {
        case 0:
            echo implode("\n",(array)$this->strHTML);
            break;
        case 1:
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=$this->filename.csv");
            echo implode((array)$this->strLine);
            unset($this->filename,$this->strLine);
            break;
        case 2:
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=$this->filename.pdf");
            $proc = proc_open("htmldoc --links --header . --linkstyle plain --numbered --size letter --no-localfiles -t pdf14 --quiet --jpeg --webpage --size letter --left 0.25in --right 0.25in --top 0.25in --bottom 0.25in --header ... --footer ... -", array(0 => array("pipe", "r"), 1 => array("pipe", "w")), $pipes);
            fwrite($pipes[0], sprintf('<html><body>%s</body></html>',implode("\n",(array)$this->strHTML)));
            fclose($pipes[0]);
            fpassthru($pipes[1]);
            $status = proc_close($proc);
            unset($status,$this->strHTML);
            break;
        case 3:
            $SchemaSave = FOGCore::getClass('Schema');
            $SchemaSave->send_file($SchemaSave->export_db());
            unset($SchemaSave);
            break;
        case 4:
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename={$type}_export.csv");
            echo implode((array)$this->strLine);
            unset($this->strLine);
            break;
        case 5:
            while (ob_get_level()) ob_end_clean();
            $filename = 'fog_backup.sql';
            $path = sprintf('%s/management/other/',BASEPATH);
            $filepath = "{$path}{$filename}";
            $ip = preg_replace('#p:#','',DATABASE_HOST);
            if (false === filter_var($ip,FILTER_VALIDATE_IP)) $ip = gethostbyname($ip);
            if (filter_var($ip,FILTER_VALIDATE_IP) === false) return;
            $cmd = sprintf("mysqldump --opt -u%s -h'$ip' %s > $filepath",escapeshellarg(DATABASE_USERNAME),escapeshellarg(DATABASE_NAME));
            if (DATABASE_PASSWORD) $cmd = sprintf("mysqldump --opt -u%s -p%s -h'$ip' %s > %s",escapeshellarg(DATABASE_USERNAME),escapeshellarg(DATABASE_PASSWORD),escapeshellarg(DATABASE_NAME),escapeshellarg($filepath));
            exec($cmd);
            $filesize = filesize($filepath);
            if (($fh = fopen($filepath,'rb')) === false) return;
            header("X-Sendfile: $filepath");
            header('Content-Type: application/octet-stream');
            header("Content-Length: $filesize");
            header("Content-Disposition: attachment; filename=$filename");
            while (feof($fh) === false) {
                $line = fread($fh,4096);
                echo $line;
            }
            fclose($fh);
            $cmd = sprintf("rm -rf %s",escapeshellarg($filepath));
            exec($cmd);
        }
    }
}
