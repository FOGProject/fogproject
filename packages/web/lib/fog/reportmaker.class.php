<?php
/**
 * Group manager mass management class.
 *
 * PHP version 5
 *
 * @category ReportMaker
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Group manager mass management class.
 *
 * @category ReportMaker
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ReportMaker extends FOGBase
{
    /**
     * Store the html string.
     *
     * @var array
     */
    private $_strHTML = array();
    /**
     * Store the csv entries.
     *
     * @var array
     */
    private $_strCSV = array();
    /**
     * Stores the line.
     *
     * @var array
     */
    private $_strLine = array();
    /**
     * Stores the filename to use.
     *
     * @var string
     */
    private $_filename = '';
    /**
     * The types of exports.
     *
     * @var array
     */
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
    /**
     * Initializes our report object.
     */
    public function __construct()
    {
        parent::__construct();
        self::$HookManager
            ->processEvent(
                'REPORT_TYPES',
                array('types' => &$this->types)
            );
    }
    /**
     * Appends html as/where required.
     *
     * @param string $html the string to add
     *
     * @return object
     */
    public function appendHTML($html)
    {
        $this->_strHTML[] = $html;

        return $this;
    }
    /**
     * Adds a CSV column.
     *
     * @param string $item the data to add
     *
     * @return object
     */
    public function addCSVCell($item)
    {
        $this->_strCSV[] = $item;

        return $this;
    }
    /**
     * Ends the current row.
     *
     * @return object
     */
    public function endCSVLine()
    {
        $this->_strLine[] = sprintf(
            '"%s"%s',
            implode(
                '","',
                $this->_strCSV
            ),
            "\n"
        );
        unset($this->_strCSV);

        return $this;
    }
    /**
     * Sets the filename.
     *
     * @param string $filename the name of the file
     *
     * @return object
     */
    public function setFileName($filename)
    {
        $this->_filename = $filename;

        return $this;
    }
    /**
     * Output the report.
     *
     * @param int $intType the type of report
     *
     * @return void
     */
    public function outputReport($intType = 0)
    {
        $keys = array_keys($this->types);
        if (!(isset($_REQUEST['type'])
            && is_string($_REQUEST['type']))
        ) {
            $_REQUEST['type'] = $keys[$intType];
        }
        $type = Initiator::sanitizeItems($_REQUEST['type']);
        if (!in_array($type, $keys)) {
            die(_('Invalid type'));
        }
        if (!(isset($_REQUEST['filename'])
            && is_string($_REQUEST['filename']))
        ) {
            $_REQUEST['filename'] = '';
        }
        $file = Initiator::sanitizeItems($_REQUEST['filename']);
        $file = basename(
            trim(
                $file
            )
        );
        if (!isset($_REQUEST['export'])) {
            $this->setFileName($file);
        }
        $intType = (
            $intType !== false ?
            (
                isset($_REQUEST['export']) ?
                3 :
                $this->types[$type]
            ) :
            0
        );
        switch ($intType) {
        case 0:
            echo implode("\n", (array) $this->_strHTML);
            break;
        case 1:
            $filename = $this->_filename;
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=$filename.csv");
            echo implode((array) $this->_strLine);
            unset($filename, $this->_strLine);
            break;
        case 2:
            $filename = $this->_filename;
            $htmlfile = sprintf(
                '%s.html',
                $filename
            );
            $html = sprintf(
                '<html><body>%s</body></html>',
                implode((array)$this->_strHTML)
            );
            $logoimage = trim(
                self::getSetting('FOG_CLIENT_BANNER_IMAGE')
            );
            if ($logoimage) {
                $logoimage = sprintf(
                    '--logoimage %s',
                    escapeshellarg(
                        sprintf(
                            'http%s://%s/fog/management/other/%s',
                            (
                                filter_input(INPUT_SERVER, 'HTTPS') ?
                                's' :
                                ''
                            ),
                            filter_input(INPUT_SERVER, 'HTTP_HOST'),
                            $logoimage
                        )
                    )
                );
            }
            $cmd = array(
                'htmldoc',
                '--webpage',
                '--quiet',
                '--gray',
                $logoimage,
                '--header l',
                '--footer D1/1',
                '--size letter',
                '-t pdf14',
                '--no-compression',
                $htmlfile
            );
            $cmd = implode(' ', (array)$cmd);
            if (!$handle = fopen($htmlfile, 'w')) {
                break;
            }
            if (!fwrite($handle, $html)) {
                fclose($handle);
                unlink($htmlfile);
            }
            fclose($handle);
            ob_start();
            passthru($cmd);
            $pdf = ob_get_clean();
            unlink($htmlfile);
            header('Content-type: application/pdf');
            header("Content-Disposition: attachment; filename=$filename.pdf");
            echo $pdf;
            unset(
                $pdf,
                $html,
                $htmlfile,
                $this->_strHTML
            );
            break;
        case 3:
            $SchemaSave = self::getClass('Schema');
            $backup_name = sprintf(
                'fog_backup_%s.sql',
                self::formatTime('', 'Ymd_His')
            );
            $SchemaSave->exportdb($backup_name);
            unset($SchemaSave);
            break;
        case 4:
            header('Content-Type: application/octet-stream');
            header(
                'Content-Disposition: attachment; '
                ."filename={$type}_export.csv"
            );
            echo implode((array) $this->_strLine);
            unset($this->_strLine);
            break;
        case 5:
            while (ob_get_level()) {
                ob_end_clean();
            }
            $filename = 'fog_backup.sql';
            $path = sprintf(
                '%s%smanagement%sother%s',
                BASEPATH,
                DS,
                DS,
                DS
            );
            $filepath = "{$path}{$filename}";
            $ip = str_replace('p:', '', DATABASE_HOST);
            if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = gethostbyname($ip);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                return;
            }
            $cmd = sprintf(
                "mysqldump --opt -u%s -h'$ip' %s > $filepath",
                escapeshellarg(DATABASE_USERNAME),
                escapeshellarg(DATABASE_NAME)
            );
            if (DATABASE_PASSWORD) {
                $cmd = sprintf(
                    "mysqldump --opt -u%s -p%s -h'$ip' %s > %s",
                    escapeshellarg(DATABASE_USERNAME),
                    escapeshellarg(DATABASE_PASSWORD),
                    escapeshellarg(DATABASE_NAME),
                    escapeshellarg($filepath)
                );
            }
            exec($cmd);
            if (($fh = fopen($filepath, 'rb')) === false) {
                return;
            }
            header("X-Sendfile: $filepath");
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename=$filename");
            while (feof($fh) === false) {
                echo fgets($fh);
                flush();
            }
            fclose($fh);
            $cmd = sprintf('rm -rf %s', escapeshellarg($filepath));
            exec($cmd);
        }
    }
}
