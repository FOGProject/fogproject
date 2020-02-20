<?php
/**
 * Prints the history of all items.
 *
 * PHP Version 5
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Prints the history of all items.
 *
 * @category History_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class History_Report extends ReportManagementPage
{
    public function file()
    {
        $this->title = _('FOG History - Search');
        unset(
            $this->data,
            $this->form,
            $this->headerData,
                $this->templates,
                $this->attributes
        );
        $this->templates = array(
            '${field}',
                '${input}'
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
                array('class' => 'col-xs-8 form-group')
        );
        $userNames = self::getSubObjectIDs(
            'User',
            '',
            'name'
        );
        $userNames = array_values(
            array_filter(
                array_unique(
                    (array)$userNames
                )
            )
        );
        natcasesort($userNames);

        if (is_array($userNames) && count($userNames) > 0) {
            $userSelForm = self::selectForm(
                'usersearch',
                $userNames
            );
            unset($userNames);
        }
        $fields = array(
                '<label for="usersearch">'
                . _('Enter an user name to search for')
                . '</label>' => $userSelForm,
        '<label for="info">'
                . _('Enter a term to search for')
                . '</label>' => '<div class="input-group">'
                . '<input type="text" class="'
                . 'form-control text-input" name='
                . '"info" value="'
                . $info
                . '" autocomplete="off" id="info" />'
                . '</div>',
                '<label for="performsearch">'
                . _('Perform search')
                . '</label>' => '<button type="submit" name="performsearch" '
                . 'class="btn btn-info btn-block" id="performsearch">'
                . _('Search')
                . '</button>'
        );
        array_walk($fields, $this->fieldsToData);
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<form class="form-horizontal" method="post" action="'
            . $this->formAction
                . '">';
        $this->render(12);
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }


    /**
     * Display page.
     *
     * @return void
     */

    public function filePost()
    {
        $this->title = _('Full History Export');
        $usersearch = filter_input(
            INPUT_POST,
            'usersearch'
        );
        $info = filter_input(
            INPUT_POST,
            'info'
        );

        if (!$usersearch) {
            $usersearch = '%';
        }
        $info = '%'. $info . '%';

        array_walk(
            self::$inventoryCsvHead,
            function (&$classGet, &$csvHeader) {
                $this->ReportMaker->addCSVCell($csvHeader);
                unset($classGet, $csvHeader);
            }
        );
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('User'),
            _('Information'),
            _('Time'),
            _('IP')
        );
        $this->templates = array(
            '${createdBy}',
            '${info}',
            '${createdTime}',
            '${ip}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array()
        );
        Route::listem(
            'history',
            'id',
            'false',
            array(
            'createdBy' => $usersearch,
            'info' => $info
        )
        );
        $Historys = json_decode(
            Route::getData()
        );
        $Historys = $Historys->historys;
        $inventoryCsvHead = array(
            _('History ID') => 'id',
            _('History Info') => 'info',
            _('History User') => 'createdBy',
            _('History Time') => 'createdTime',
            _('History IP') => 'ip'
        );
        foreach ((array)$inventoryCsvHead as $head => &$classGet) {
            $this->ReportMaker->addCSVCell($head);
            unset($classGet, $head);
        }
        $this->ReportMaker->endCSVLine();
        foreach ((array)$Historys as &$History) {
            $this->data[] = array(
                'createdBy' => $History->createdBy,
                'info' => $History->info,
                'createdTime' => $History->createdTime,
                'ip' => $History->ip
            );
            foreach ((array)$inventoryCsvHead as $head => &$classGet) {
                $this->ReportMaker->addCSVCell($History->{$classGet});
                unset($classGet, $head);
            }
            $this->ReportMaker->endCSVLine();
            unset($Inventory, $Host);
        }
        $this->ReportMaker->appendHTML($this->process(12));
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        if (is_array($this->data) && count($this->data) > 0) {
            echo '<div class="text-center">';
            printf(
                $this->reportString,
                'HistoryReport',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'HistoryReport',
                _('Export PDF'),
                _('Export PDF'),
                self::$pdffile
            );
            echo '</div>';
        }
        $this->ReportMaker->outputReport(0, true);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
