<?php
/**
 * Prints equipment loan.
 *
 * PHP version 5
 *
 * @category Equipment_Loan
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Prints equipment loan.
 *
 * @category Equipment_Loan
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Equipment_Loan extends ReportManagementPage
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('FOG Equipment Loan Form');
        unset($this->headerData);
        $this->templates = array(
            '${field}',
            '${input}',
        );
        $this->attributes = array(
            array('class' => 'col-xs-4'),
            array('class' => 'col-xs-8 form-group'),
        );
        Route::listem('inventory');
        $Inventories = json_decode(
            Route::getData()
        );
        $Inventories = $Inventories->inventorys;
        $pusers = array();
        $puser = filter_input(INPUT_POST, 'user');
        foreach ((array)$Inventories as &$Inventory) {
            if (!$Inventory->primaryUser) {
                continue;
            }
            $pusers[$Inventory->id] = $Inventory->primaryUser;
            unset($Inventory);
        }
        $selUser = self::selectForm(
            'user',
            $pusers,
            $puser,
            true
        );
        $fields = array(
            '<label for="user">'
            . _('Select User')
            . '</label>' => $selUser,
            '<label for="createReport">'
            . _('Create Report?')
            . '</label>' => '<button class="btn btn-info btn-block" type="submit" '
            . 'id="createReport">'
            . _('Generate')
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
     * Post page
     *
     * @return void
     */
    public function filePost()
    {
        $Inventory = new Inventory(
            filter_input(INPUT_POST, 'user')
        );
        if (!$Inventory->isValid()) {
            return;
        }
        $this->title = _('FOG Equipment Loan Form');
        list(
            $coname,
            $subname,
            $tos
        ) = self::getSubObjectIDs(
            'Service',
            array(
                'name' => array(
                    'FOG_COMPANY_NAME',
                    'FOG_COMPANY_SUBNAME',
                    'FOG_COMPANY_TOS'
                )
            ),
            'value',
            false,
            'AND',
            'name',
            false,
            ''
        );
        $this->ReportMaker->appendHTML(
            sprintf(
                '<!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" -->'
                . '<p class="c"><h3>%s</h3></p><hr/><p class="c">'
                . '<h2>%s</h2></p><p class="c"><h3>%s</h3></p>'
                . '<p class="c"><h2><u>%s</u></h2></p>'
                . '<p class="c"><h4><u>%s</u></h4></p>'
                . '<h4><b>%s: </b><u>%s</u></h4><h4>'
                . '<b>%s: </b><u>%s</u></h4><h4>'
                . '<b>%s: </b>%s</h4><h4><b>%s: </b>'
                . '%s</h4><h4><b>%s: </b>%s</h4><h4>'
                . '<b>%s: </b>%s</h4><p class="c">'
                . '<h4><u>%s</u></h4></p><h4><b>%s: </b>'
                . '<u>%s</u></h4><h4><b>%s: </b><u>%s</u>'
                . '</h4><h4><b>%s: </b><u>%s</u></h4>'
                . '<p class="c"><h4><b>%s / %s / %s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<p class="c"><h4><b>%s</b></h4></p>'
                . '<br/><hr/><h4><b>%s: </b>%s</h4>'
                . '<p class="c"><h4>(%s %s)</h4></p>'
                . '<p class="c"><h4>%s</h4></p><h4><b>%s: </b>%s</h4>'
                . '<h4><b>%s: </b>%s</h4>'
                . '<!-- NEW PAGE -->'
                . '<!-- FOOTER CENTER "$PAGE %s $PAGES - %s: %s" -->'
                . '<p class="c"><h3>%s</h3></p><hr/><h4>%s</h4>'
                . '<h4><b>%s: </b>%s</h4><h4><b>%s: </b>%s</h4>',
                _('of'),
                _('Printed'),
                self::formatTime('', 'D M j G:i:s T Y'),
                _('Equipment Loan'),
                $coname,
                $subname,
                _('PC Check-out Agreement'),
                _('Personal Information'),
                _('Name'),
                $Inventory->get('primaryUser'),
                _('Location'),
                _('Your Location Here'),
                str_pad(_('Home Address'), 25),
                str_repeat('_', 65),
                str_pad(_('City/State/Zip'), 25),
                str_repeat('_', 65),
                str_pad(_('Extension'), 25),
                str_repeat('_', 65),
                str_pad(_('Home Phone'), 25),
                str_repeat('_', 65),
                _('Computer Information'),
                str_pad(
                    sprintf(
                        '%s / %s',
                        _('Serial Number'),
                        _('Service Tag')
                    ),
                    25
                ),
                str_pad(
                    sprintf(
                        '%s / %s',
                        $Inventory->get('sysserial'),
                        $Inventory->get('caseasset')
                    ),
                    65,
                    '_'
                ),
                str_pad(
                    _('Barcode Numbers'),
                    25
                ),
                str_pad(
                    sprintf(
                        '%s %s',
                        $Inventory->get('other1'),
                        $Inventory->get('other2')
                    ),
                    65,
                    '_'
                ),
                str_pad(
                    _('Date of checkout'),
                    25
                ),
                str_repeat('_', 65),
                _('Notes'),
                _('Miscellaneous'),
                _('Included Items'),
                str_repeat('_', 75),
                str_repeat('_', 75),
                str_repeat('_', 75),
                str_pad(_('Releasing Staff Initials'), 25),
                str_repeat('_', 65),
                _('To be released only by'),
                str_repeat('_', 20),
                sprintf(
                    '%s, %s, %s %s %s.',
                    _('I have read'),
                    _('understood'),
                    _('and agree to all the'),
                    _('Terms and Conditions'),
                    _('on the following pages of this document')
                ),
                str_pad(_('Signed'), 25),
                str_repeat('_', 65),
                str_pad(_('Date'), 25),
                str_repeat('_', 65),
                _('of'),
                _('Printed'),
                self::formatTime('', 'D M j G:i:s T Y'),
                _('Terms and Conditions'),
                $tos,
                str_pad(_('Signed'), 25),
                str_repeat('_', 65),
                str_pad(_('Date'), 25),
                str_repeat('_', 65)
            )
        );
        echo '<div class="col-xs-9">';
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo $this->title;
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        echo '<div class="text-center">';
        echo '<h4 class="title">';
        echo '<div id="exportDiv"></div>';
        echo '<a id="pdfsub" href="../management/export.php?type=pdf&filename='
            . $Inventory->get('primaryUser')
            . 'EquipmentLoadForm" alt="'
            . _('Export PDF')
            . '" title="'
            . _('Export PDF')
            . '" target="_blank" data-toggle="tooltip" data-placement="top">'
            . self::$pdffile
            . '</a>';
        echo '</h4>';
        echo '</div>';
        echo _('Your form is ready');
        echo '<div class="panel panel-info">';
        echo '<div class="panel-heading text-center">';
        echo '<h4 class="title">';
        echo _('Form');
        echo '</h4>';
        echo '</div>';
        echo '<div class="panel-body">';
        $this->ReportMaker->outputReport(0, true);
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        $_SESSION['foglastreport'] = serialize($this->ReportMaker);
    }
}
