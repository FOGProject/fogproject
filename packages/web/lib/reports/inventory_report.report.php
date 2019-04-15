<?php
/**
 * Prints the inventory of all items.
 *
 * PHP Version 5
 *
 * @category Inventory_Report
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Inventory_Report extends ReportManagementPage
{
    /**
     * Display search page.
     *
     * @return void
     */

    public function file()
    {
        $this->title = _('FOG Host Inventory - Search');
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
        $groupNames = self::getSubObjectIDs(
                'Group',
                '',
                'name'
        );
        $groupNames = array_values(
                array_filter(
                        array_unique(
                                (array)$groupNames
                        )
                 )
        );
        if (in_array('location', (array)self::$pluginsinstalled)) {
            $locationNames = self::getSubObjectIDs(
                        'Location',
                        '',
                        'name'
                );
            natcasesort($locationNames);
            if (count($locationNames) > 0) {
                $locationSelForm = self::selectForm(
                                'locationsearch',
                                $locationNames
                        );
                unset($locationNames);
            }
        }
        if (in_array('site', (array)self::$pluginsinstalled)) {
            $siteNames = self::getSubObjectIDs(
                        'site',
                        '',
                        'name'
                );
            natcasesort($siteNames);
            if (count($siteNames) > 0) {
                $siteSelForm = self::selectForm(
                                'sitesearch',
                                $siteNames
                        );
                unset($siteNames);
            }
        }
        $sysproductNames = self::getSubObjectIDs(
                        'inventory',
                        '',
                        'sysproduct'
                );
        $sysproductNames = array_values(
                array_filter(
                        array_unique(
                                (array)$sysproductNames
                        )
                 )
        );
        natcasesort($groupNames);
        natcasesort($sysproductNames);

        if (count($groupNames) > 0) {
            $groupSelForm = self::selectForm(
                        'groupsearch',
                        $groupNames
                );
            unset($groupNames);
        }
        if (count($sysproductNames) > 0) {
            $sysproductSelForm = self::selectForm(
                        'sysproductsearch',
                        $sysproductNames
                );
            unset($sysproductNames);
        }

        $fields = array(
                '<label for="groupsearch">'
                . _('Enter a group name to search for')
                . '</label>' => $groupSelForm,
        '<label for="sysproductsearch">'
                . _('Enter a model name to search for')
                . '</label>' => $sysproductSelForm,
                '<label for="performsearch">'
                . _('Perform search')
                . '</label>' => '<button type="submit" name="performsearch" '
                . 'class="btn btn-info btn-block" id="performsearch">'
                . _('Search')
                . '</button>'
        );
        if (in_array('location', (array)self::$pluginsinstalled)) {
            self::arrayInsertAfter(
                        '<label for="groupsearch">'
                        . _('Enter a group name to search for')
                        . '</label>',
                        $fields,
                        '<label for="locationsearch">'
                        . _('Enter a location name to search for')
                        . '</label>',
                        $locationSelForm
                );
        }
        if (in_array('site', (array)self::$pluginsinstalled)) {
            self::arrayInsertAfter(
                        '<label for="groupsearch">'
                        . _('Enter a group name to search for')
                        . '</label>',
                        $fields,
                        '<label for="sitesearch">'
                        . _('Enter a site name to search for')
                        . '</label>',
                        $siteSelForm
                );
        }
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
        $this->title = _('Full Inventory Export');
        $groupsearch = filter_input(
             INPUT_POST,
             'groupsearch'
        );
        if (!$groupsearch) {
            $groupsearch = '%';
        }

        $locationsearch = filter_input(
             INPUT_POST,
             'locationsearch'
        );
        $sitesearch = filter_input(
             INPUT_POST,
             'sitesearch'
        );
        $sysproductsearch = filter_input(
             INPUT_POST,
             'sysproductsearch'
        );
        if (!$sysproductsearch) {
            $sysproductsearch = '%';
        }

        array_walk(
            self::$inventoryCsvHead,
            function (&$classGet, &$csvHeader) {
                $this->ReportMaker->addCSVCell($csvHeader);
                unset($classGet, $csvHeader);
            }
        );
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Host name'),
            _('Memory'),
            _('System Product'),
            _('System Serial')
        );
        $this->templates = array(
            '${host_name}<br/><small>${host_mac}</small>',
            '${memory}',
            '${sysprod}',
            '${sysser}'
        );
        $this->attributes = array(
            array(),
            array(),
            array(),
            array()
        );

        $groupIDs = self::getSubObjectIDs(
                'Group',
                array('name' => $groupsearch),
                'id'
        );

        $groupHostIDs = self::getSubObjectIDs(
                'GroupAssociation',
                array('groupID' => $groupIDs),
                'hostID'
        );
        if (in_array('location', (array)self::$pluginsinstalled) && $locationsearch) {
            $locationIDs = self::getSubObjectIDs(
                        'Location',
                        array('name' => $locationsearch),
                        'id'
                );
            $locationHostIDs = self::getSubObjectIDs(
                        'LocationAssociation',
                        array('locationID' => $locationIDs),
                        'hostID'
                );
            $groupHostIDs = array_intersect($locationHostIDs, $groupHostIDs);
        }
        if (in_array('site', (array)self::$pluginsinstalled) && $sitesearch) {
            $siteIDs = self::getSubObjectIDs(
                        'Site',
                        array('name' => $sitesearch),
                        'id'
                );
            $siteHostIDs = self::getSubObjectIDs(
                        'SiteHostAssociation',
                        array('siteID' => $siteIDs),
                        'hostID'
                );
            $groupHostIDs = array_intersect($siteHostIDs, $groupHostIDs);
        }
        $sysproductIDs = self::getSubObjectIDs(
                'Inventory',
                array('sysproduct' => $sysproductsearch),
                'hostID'
        );
        $groupHostIDs = array_intersect($sysproductIDs, $groupHostIDs);

        Route::listem(
            'host',
                'name',
                'false',
                array(
                        'id' => $groupHostIDs
                )
        );

        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
        foreach ((array)$Hosts as &$Host) {
            $Image = $Host->image;
            $Inventory = $Host->inventory;
            $this->data[] = array(
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'memory' => $Inventory->memory,
                'sysprod' => $Inventory->sysproduct,
                'sysser' => $Inventory->sysserial,
            );
            foreach (self::$inventoryCsvHead as $head => &$classGet) {
                switch ($head) {
                case _('Host ID'):
                    $this->ReportMaker->addCSVCell($Host->id);
                    break;
                case _('Host name'):
                    $this->ReportMaker->addCSVCell($Host->name);
                    break;
                case _('Host MAC'):
                    $this->ReportMaker->addCSVCell($Host->primac);
                    break;
                case _('Host Desc'):
                    $this->ReportMaker->addCSVCell($Host->description);
                    break;
                case _('Memory'):
                    $this->ReportMaker->addCSVCell($Inventory->memory);
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Inventory->$classGet);
                    break;
                }
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
        if (count($this->data) > 0) {
            echo '<div class="text-center">';
            printf(
                $this->reportString,
                'InventoryReport',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'InventoryReport',
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
