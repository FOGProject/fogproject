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
class Equipment_Loan extends ReportManagement
{
    /**
     * Display page.
     *
     * @return void
     */
    public function file()
    {
        $this->title = _('Equipment Loan');

        $puser = filter_input(INPUT_POST, 'user');
        $pusers = [];

        Route::listem('inventory');
        $Inventories = json_decode(
            Route::getData()
        );
        foreach ($Inventories->data as &$Inventory) {
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
            true,
            'select2'
        );

        $labelClass = 'col-sm-2 control-label';

        $fields = [
            self::makeLabel(
                $labelClass,
                'user',
                _('Select User')
            ) => $selUser
        ];

        $buttons = self::makeButton(
            'selectuser',
            _('Create Form'),
            'btn btn-primary'
        );
        $buttons .= self::makeButton(
            'downloadpdf',
            _('Download PDF'),
            'btn btn-success hidden'
        );
        $buttons .= self::makeButton(
            'printpdf',
            _('Print PDF'),
            'btn btn-warning hidden'
        );

        self::$HookManager->processEvent(
            'EQUIPMENTLOAN_SELECT_FIELDS',
            [
                'fields' => &$fields,
                'buttons' => &$buttons
            ]
        );
        $rendered = self::formFields($fields);
        unset($fields);

        echo self::makeFormTag(
            'form-horizontal',
            'equipmentloan-form',
            $this->formAction,
            'post',
            'application/x-www-form-urlencoded',
            true
        );
        echo '<div class="box box-solid">';
        echo '<div class="box-header with-border">';
        echo '<h4 class="box-title">';
        echo _('Select User');
        echo '</h4>';
        echo '</div>';
        echo '<div class="box-body">';
        echo $rendered;
        echo '</div>';
        echo '<div class="box-footer with-border">';
        echo '<div class="btn-group">';
        echo $buttons;
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</form>';
    }
    /**
     * Post page
     *
     * @return void
     */
    public function filePost()
    {
        header('Content-type: application/json');
        self::$HookManager->processEvent('EQUIPMENTLOAN_POST');
        $user = trim(
            filter_input(INPUT_POST, 'user')
        );
        try {
            $Inventory = new Inventory($user);
            if (!$Inventory->isValid()) {
                throw new Exception(
                    _('Selected User is invalid')
                );
            }
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
            $date = self::formatTime('', 'Y-m-d');

            $data = [
                'msg' => _('Form created!'),
                'title' => _('Equipment Loan Form'),
                '_data' => [
                    'head' => _('Equipment Loan Form'),
                    'foot' => _('Equipment Loan Form'),
                    'filename' => 'EquipmentLoan_'
                    . $Inventory->get('primaryUser')
                    . '_'
                    . $date
                    . '.pdf',
                    'content' => [
                        _('Hello World'),
                        [
                            'text' => _('Hello World'),
                            'fontSize' => 15
                        ]
                    ]
                ]
            ];
            echo json_encode($data);
            exit;

            $content = sprintf(
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
            );
            $code = HTTPResponseCodes::HTTP_ACCEPTED;
            $hook = 'EQUIPMENTLOAN_GENERATE_SUCCESS';
            $msg = json_encode(
                [
                    'msg' => _('Form Generated'),
                    'title' => _('Equipment Loan Form'),
                    'content' => $content
                ]
            );
        } catch (Exception $e) {
            $code = HTTPResponseCodes::HTTP_BAD_REQUEST;
            $hook = 'EQUIPMENTLOAN_GENERATE_FAIL';
            $msg = json_encode(
                [
                    'error' => $e->getMessage(),
                    'title' => _('Generate Form Fail')
                ]
            );
        }
        http_response_code($code);
        echo $msg;
        exit;
    }
}
