<?php
/**
 * Reports hosts within.
 *
 * PHP version 5
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Fernando Gietz <fernando.gietz@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Reports hosts within.
 *
 * @category Host_List
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Host_List extends ReportManagementPage
{
    /**
     * Display search page.
     *
     * @return void
     */

    public function file()
    {
        $this->title = _('FOG Host - Search');
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
        $fields = array();
        $groupNames = self::getSubObjectIDs(
            'Group',
            '',
            'name'
        );
        if (is_array($groupNames) && count($groupNames) > 0) {
            $groupNames = array_values(
                array_filter(
                    array_unique(
                        (array)$groupNames
                    )
                )
            );
            natcasesort($groupNames);
            $groupSelForm = self::selectForm(
                'groupsearch',
                $groupNames
            );
            unset($groupNames);
            $fields += array('<label for="groupsearch">'
                . _('Enter a group name to search for')
                . '</label>' => $groupSelForm
            );
        }
        if (in_array('location', (array)self::$pluginsinstalled)) {
            $locationNames = self::getSubObjectIDs(
                'Location',
                '',
                'name'
            );
            if (is_array($locationNames) && count($locationNames) > 0) {
                natcasesort($locationNames);
                $locationSelForm = self::selectForm(
                    'locationsearch',
                    $locationNames
                );
                unset($locationNames);
                $fields += array('<label for="locationsearch">'
                    . _('Enter a location name to search for')
                    . '</label>' => $locationSelForm
                );
            }
        }
        if (in_array('site', (array)self::$pluginsinstalled)) {
            $siteNames = self::getSubObjectIDs(
                'site',
                '',
                'name'
            );
            if (is_array($siteNames) && count($siteNames) > 0) {
                natcasesort($siteNames);
                $siteSelForm = self::selectForm(
                    'sitesearch',
                    $siteNames
                );
                unset($siteNames);
                $fields += array('<label for="sitesearch">'
                    . _('Enter a site name to search for')
                    . '</label>' => $siteSelForm
                );
            }
        }
        $fields += array('<label for="hostpattern">'
            . _('Search pattern') . '</label>'
            => '<input type="text" name="hostpattern" placeholder="Search... leave empty for all elements" class="form-control" />'
        ) + array('<label for="performsearch">'
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
        $this->title = _('Host Listing Export');
        $groupsearch = filter_input(
            INPUT_POST,
            'groupsearch'
        );
    
        $locationsearch = filter_input(
            INPUT_POST,
            'locationsearch'
        );
        $sitesearch = filter_input(
            INPUT_POST,
            'sitesearch'
        );
        $hostpattern = filter_input(
            INPUT_POST,
            'hostpattern'
        );
        if (!$hostpattern) {
            $hostpattern = '%';
        } else {
            $hostpattern = '%' . $hostpattern . '%';
        }
        $csvHead = array(
            _('Host ID') => 'id',
            _('Host Name') => 'name',
            _('Host Desc') => 'description',
            _('Host MAC') => 'primac',
            _('Host Created') => 'createdTime',
            _('Host AD Join') => 'useAD',
            _('Host AD OU') => 'ADOU',
            _('Host AD Domain') => 'ADDomain',
            _('Host Kernel') => 'kernel',
            _('Host HD Device') => 'kernelDevice',
            _('Image ID') => 'id',
            _('Image Name') => 'name',
            _('Image Desc') => 'description',
            _('OS Name') => 'name',
        );
        foreach ((array)$csvHead as $csvHeader => &$classGet) {
            $this->ReportMaker->addCSVCell($csvHeader);
            unset($classGet);
        }
        $this->ReportMaker->endCSVLine();
        $this->headerData = array(
            _('Hostname'),
            _('Host MAC'),
            _('Image Name'),
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${image_name}',
        );
        $groupHostIDs = array();
        if ($groupsearch) {
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
        }
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

        if ($groupsearch) {
            Route::listem(
                'host',
                'name',
                'false',
                array(
                    'id' => $groupHostIDs,
                    'name' => $hostpattern
                )
            );
        } else {
            Route::listem(
                'host',
                'name',
                'false',
                array('name' => $hostpattern)
            );
        }
        $Hosts = json_decode(
            Route::getData()
        );
        $Hosts = $Hosts->hosts;
        foreach ((array)$Hosts as &$Host) {
            $Image = $Host->image;
            $imgID = $Image->id;
            $imgName = $Image->name;
            $imgDesc = $Image->description;
            unset($Image);
            $this->data[] = array(
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'image_name' => $imgName,
            );
            foreach ((array)$csvHead as $head => &$classGet) {
                switch ($head) {
                case _('Image ID'):
                    $this->ReportMaker->addCSVCell($imgID);
                    break;
                case _('Image Name'):
                    $this->ReportMaker->addCSVCell($imgName);
                    break;
                case _('Image Desc'):
                    $this->ReportMaker->addCSVCell($imgDesc);
                    break;
                case _('Host AD Join'):
                    $this->ReportMaker->addCSVCell(
                        (
                            $Host->useAD == 1 ?
                            _('Yes') :
                            _('No')
                        )
                    );
                    break;
                default:
                    $this->ReportMaker->addCSVCell($Host->$classGet);
                    break;
                }
                unset($classGet);
            }
            unset($Host);
            $this->ReportMaker->endCSVLine();
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
                'HostList',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'HostList',
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
