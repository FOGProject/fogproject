<?php
/**
 * Presents the page the same to all.
 *
 * PHP version 5
 *
 * @category Index
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Presents the page the same to all.
 *
 * @category Index
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

$isLoggedIn = self::$FOGUser->isValid();
?>

<!DOCTYPE html>
<html lang="<?php echo ProcessLogin::getLocale(); ?>">
<head>
    <meta charset="utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="theme-color" content="#367fa9"/>
    <title><?php echo $this->pageTitle; ?></title>

    <?php

    self::$HookManager
        ->processEvent(
            'CSS',
            ['stylesheets' => &$this->stylesheets]
        );
    foreach ((array)$this->stylesheets as &$stylesheet) {
        echo '<link href="'
            . $stylesheet
            . '?ver='
            . FOG_BCACHE_VER
            . '" rel="stylesheet" type="text/css"/>';
        unset($stylesheet);
    }

    unset($this->stylesheets);

    ?>

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->

    <!--[if lt IE 9]>
    <script src="dist/js/html5shiv.min.js"></script>
    <script src="dist/js/respond.min.js"></script>
    <![endif]-->

</head>
<body class="<?php echo ($isLoggedIn) ? 'hold-transition skin-blue sidebar-mini' : 'hold-transition login-page' ?>">
    <!-- FOG Management only works when JavaScript is enabled. -->
    <noscript>
        <div id="noscriptMessage">
            <p>You must enable JavaScript to use FOG management.</p>
        </div>

        <style>
            body > *:not(noscript) {
                display: none;
            }

            #noscriptMessage {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 24px;
            }
        </style>
    </noscript>

    <div class="wrapper">
        <!-- Header Navigation -->
        <header class="main-header">
            <?php if($isLoggedIn) { ?>
                <a href="./index.php" class="logo">
                <span class="logo-mini"><b>FOG</b></span>
                <span class="logo-lg"><b>FOG</b> Project</span>
                </a>
            <?php } ?>

            <nav class="navbar navbar-static-top">
            <?php if ($isLoggedIn) { ?>
                <p class="mobile-logo">
                    <a href="../management/index.php">
                        <b><?php echo _('FOG') ?></b><?php echo _('Project') ?>
                    </a>
                </p>

                <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </a>
            <?php } ?>

                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <li>
                            <?php if($isLoggedIn){ ?>
                                <a href="../management/index.php?node=logout">
                                    <i class="fa fa-sign-out"></i> <?php echo _('Logout'); ?>
                                </a>
                            <?php }else{ ?>
                                <?php

                                    global $node;
                                    if ($node != 'home') {

                                 ?>
                                    <a href="../management/index.php?node=login">
                                        <i class="fa fa-sign-in"></i> <?php echo _('Login'); ?>
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>

        <?php if($isLoggedIn){ ?>
            <!-- SIDEBAR NAVIGATION -->
            <aside class="main-sidebar">
                <section class="sidebar">
                    <div class="user-panel">
                        <div>
                            <a
                                href="../management/index.php?node=user&sub=edit&id=<?php echo self::$FOGUser->get('id'); ?>"
                                class="fog-user">
                                    <?php echo self::$FOGUser->getDisplayName(); ?>
                            </a>
                        </div>
                    </div>

                    <?php
                        echo FOGPage::makeFormTag(
                            'sidebar-form',
                            'universal-search-form',
                            '../../fog/unisearch',
                            'post',
                            'application/x-www-form-urlencoded',
                            true
                        );
                    ?>
                        <div>
                            <select id="universal-search-select" class="form-control" name="search" data-placeholder="<?php echo _('Search'); ?>..."></select>
                        </div>
                    </form>

                    <ul class="sidebar-menu" data-widget="tree">
                        <li class="header"><?php echo _('MAIN NAVIGATION'); ?></li>
                        <?php echo $this->menu; ?>

                        <?php if(self::$pluginIsAvailable){ ?>
                            <li class="header">
                                <span class="pull-left"><?php echo _('PLUGIN OPTIONS'); ?></span>
                                <span class="pull-right-container">
                                    <a href="#" class="plugin-options-alternate">
                                        <i class="fa fa-minus"></i>
                                    </a>
                                </span>
                            </li>

                            <div class="sidebar-menu plugin-options">
                                <?php echo $this->menuHook; ?>
                            </div>
                        <?php } ?>

                        <li class="header"><?php echo _('RESOURCES'); ?></li>
                        <li>
                            <a href="https://sourceforge.net/donate/index.php?group_id=201099" target="_blank">
                                <i class="fa fa-money"></i> <span><?php echo _('Donate'); ?></span>
                            </a>
                        </li>

                        <li>
                            <a href="https://news.fogproject.org" target="_blank">
                                <i class="fa fa-bullhorn"></i> <span><?php echo _('News'); ?></span>
                            </a>
                        </li>

                        <li>
                            <a href="https://forums.fogproject.org" target="_blank">
                                <i class="fa fa-users"></i> <span><?php echo _('Forums'); ?></span>
                            </a>
                        </li>

                        <li>
                            <a href="https://wiki.fogproject.org" target="_blank">
                                <i class="fa fa-book"></i> <span><?php echo _('Wiki'); ?></span>
                            </a>
                        </li>
                    </ul>
                </section>
            </aside>
        <?php } ?>

        <!-- Main Content -->
        <?php if($isLoggedIn){ ?>
            <div class="content-wrapper">
                <?php
                    echo FOGPage::makeInput(
                        'reAuthDelete',
                        'reAuthDelete',
                        '',
                        'hidden',
                        'reAuthDelete',
                        self::getSetting('FOG_REAUTH_ON_DELETE')
                    );

                    $pageLength = self::getSetting('FOG_VIEW_DEFAULT_SCREEN');

                    if (in_array(strtolower($pageLength), ['search','list'])) {
                        $pageLength = 10;
                        $Service = self::getClass('Service')
                            ->set('name', 'FOG_VIEW_DEFAULT_SCREEN')
                            ->load('name')
                            ->set(
                                'description',
                                _(
                                    'This setting defines the number of items to display '
                                    . 'when listing/searching elements. The default value is 10.'
                                )
                            )->set('value', $pageLength)
                            ->save();
                        unset($Service);
                    }
                    echo FOGPage::makeInput(
                        'pageLength',
                        'pageLength',
                        '',
                        'hidden',
                        'pageLength',
                        self::getSetting('FOG_VIEW_DEFAULT_SCREEN')
                    );
                    echo FOGPage::makeInput(
                        'showpass',
                        'showpass',
                        '',
                        'hidden',
                        'showpass',
                        self::getSetting('FOG_ENABLE_SHOW_PASSWORDS')
                    );
                ?>

                <section class="content-header">
                    <h1 id="sectionTitle">
                        <?php echo $this->sectionTitle; ?>
                        <small id="pageTitle"><?php echo $this->pageTitle; ?></small>
                    </h1>
                </section>

                <section class="content">
                    <?php echo $this->body; ?>
                </section>
            </div>
        <?php }else {
            echo $this->body;
        } ?>

        <!-- Footer -->
        <?php if($isLoggedIn) { ?>
            <footer class="main-footer">
                <div class="pull-right hidden-xs">
                    <b><?php echo _('Channel'); ?></b>
                    <?php echo FOG_CHANNEL; ?> |

                    <a href="../management/index.php?node=about&sub=home" style="text-decoration: none">
                        <b><?php echo _('Version'); ?></b> <?php echo FOG_VERSION; ?>
                    </a>
                </div>

                <strong>
                    <?php echo _('Copyright'); ?> &copy; 2012-2018 <a href="https://fogproject.org">FOG Project</a>
                </strong>
                <?php _('All rights reserved.'); ?>
            </footer>
        <?php } ?>
    </div>

    <?php

    foreach ((array)$this->javascripts as &$javascript) {
        echo '<script src="'
            . $javascript
            . '?ver='
            . FOG_BCACHE_VER
            . '" type="text/javascript"></script>';
        unset($javascript);
    }

    unset($this->javascripts);

    ?>

    <!-- Memory Usage: <?php echo self::formatByteSize(memory_get_usage(true)); ?> -->
    <!-- Memory Peak: <?php echo self::formatByteSize(memory_get_peak_usage()); ?> -->

</body>
</html>
