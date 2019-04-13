<?php
/**
 * Reports hosts and the users within.
 *
 * PHP version 5
 *
 * @category Hosts_And_Users
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @author   Fernando Gietz <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class Hosts_And_Users extends ReportManagementPage
{
    /**
     * Display search page.
     *
     * @return void
     */

    public function file()
    {
        $this->title = _('FOG Host and Users - Search');
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
        natcasesort($groupNames);

        if (count($groupNames) > 0) {
            $groupSelForm = self::selectForm(
                        'groupsearch',
                        $groupNames
                );
            unset($groupNames);
        }
        $fields = array(
                '<label for="groupsearch">'
                . _('Enter a group name to search for')
                . '</label>' => $groupSelForm,
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
     * The page to display.
     *
     * @return void
     */
    public function filePost()
    {
        $this->title =_('FOG Hosts and Users Login');
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

        $csvHead = array(
            _('Host ID') => 'id',
            _('Host Name') => 'name',
            _('Host Desc') => 'description',
            _('Host MAC') => 'mac',
            _('Host Created') => 'createdTime',
            _('Image ID') => 'id',
            _('Image Name') => 'name',
            _('Image Desc') => 'description',
            _('AD Join') => 'useAD',
            _('AD OU') => 'ADOU',
            _('AD Domain') => 'ADDomain',
            _('Kernel') => 'kernel',
            _('HD Device') => 'kernelDevice',
            _('OS Name') => 'name',
            _('Login Users') => 'users'
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
            _('Login Users')
        );
        $this->templates = array(
            '${host_name}',
            '${host_mac}',
            '${image_name}',
            '${users}'
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
            $imgID = $Image->id;
            $imgName = $Image->name;
            $imgDesc = $Image->description;
            unset($Image);
            $this->data[] = array(
                'host_name' => $Host->name,
                'host_mac' => $Host->primac,
                'image_name' => $imgName,
                'users' => implode(
                    '<br/>',
                    self::getSubObjectIDs(
                        'UserTracking',
                        array('hostID' => $Host->id),
                        'username'
                    )
                )
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
                case _('AD Join'):
                    $this->ReportMaker->addCSVCell(
                        (
                            $Host->useAD == 1 ?
                            _('Yes') :
                            _('No')
                        )
                    );
                    break;
                case _('Login Users'):
                    $this->ReportMaker->addCSVCell(
            implode(
                        ' ',
                            self::getSubObjectIDs(
                                'UserTracking',
                                array('hostID' => $Host->id),
                                'username'
                            )
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
        if (count($this->data) > 0) {
            echo '<div class="text-center">';
            printf(
                $this->reportString,
                'Hosts_and_Users',
                _('Export CSV'),
                _('Export CSV'),
                self::$csvfile,
                'Hosts_and_Users',
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
