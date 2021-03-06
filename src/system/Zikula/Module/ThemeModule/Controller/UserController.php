<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Module\ThemeModule\Controller;

use LogUtil;
use ModUtil;
use System;
use SecurityUtil;
use UserUtil;
use ThemeUtil;
use DataUtil;
use Zikula_View;
use CookieUtil;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * User controllers for the theme module
 */
class UserController extends \Zikula_AbstractController
{
    /**
     * display theme changing user interface
     *
     * @return Response symfony response object
     *
     * @throws \RuntimeException Thrown if theme switching is currently disabled
     * @throws AccessDeniedHttpException Thrown if the user doesn't have comment permissions over the theme module
     */
    public function indexAction()
    {
        // check if theme switching is allowed
        if (!System::getVar('theme_change')) {
            throw new \RuntimeException($this->__('Notice: Theme switching is currently disabled.'));
        }

        if (!SecurityUtil::checkPermission('ZikulaThemeModule::', '::', ACCESS_COMMENT)) {
            throw new AccessDeniedHttpException();
        }

        // get our input
        $startnum = $this->request->query->get('startnum', isset($args['startnum']) ? $args['startnum'] : 1);

        // we need this value multiple times, so we keep it
        $itemsperpage = $this->getVar('itemsperpage');

        // get some use information about our environment
        $currenttheme = ThemeUtil::getInfo(ThemeUtil::getIDFromName(UserUtil::getTheme()));

        // get all themes in our environment
        $allthemes = ThemeUtil::getAllThemes(ThemeUtil::FILTER_USER);

        $previewthemes = array();
        $currentthemepic = null;
        foreach ($allthemes as $key => $themeinfo) {
            $themename = $themeinfo['name'];
            if (file_exists($themepic = 'themes/'.DataUtil::formatForOS($themeinfo['directory']).'/Resources/public/images/preview_medium.png')) {
                $themeinfo['previewImage'] = $themepic;
                $themeinfo['largeImage'] = 'themes/'.DataUtil::formatForOS($themeinfo['directory']).'/Resources/public/images/preview_large.png';
            } else {
                $themeinfo['previewImage'] = 'system/Zikula/Module/ThemeModule/Resources/public/images/preview_medium.png';
                $themeinfo['largeImage'] = 'system/Zikula/Module/ThemeModule/Resources/public/images/preview_large.png';
            }
            if ($themename == $currenttheme['name']) {
                $currentthemepic = $themepic;
                unset($allthemes[$key]);
            } else {
                $previewthemes[$themename] = $themeinfo;
            }
        }

        $previewthemes = array_slice($previewthemes, $startnum-1, $itemsperpage);

        $this->view->setCaching(Zikula_View::CACHE_DISABLED);

        $this->view->assign('currentthemepic', $currentthemepic)
                   ->assign('currenttheme', $currenttheme)
                   ->assign('themes', $previewthemes)
                   ->assign('defaulttheme', ThemeUtil::getInfo(ThemeUtil::getIDFromName(System::getVar('Default_Theme'))));

        // assign the values for the pager plugin
        $this->view->assign('pager', array('numitems' => sizeof($allthemes),
                                           'itemsperpage' => $itemsperpage));

        // Return the output that has been generated by this function
        return $this->response($this->view->fetch('User/main.tpl'));
    }

    /**
     * reset the current users theme to the site default
     *
     * @return void
     */
    public function resettodefaultAction()
    {
        ModUtil::apiFunc('ZikulaThemeModule', 'user', 'resettodefault');
        LogUtil::registerStatus($this->__('Done! Theme has been reset to the default site theme.'));

        return $this->redirect(ModUtil::url('ZikulaThemeModule', 'user', 'index'));
    }
        
    /**
     * Enable mobile Theme 
     *
     * @return void
     */
    public function enableMobileTheme()
    {
        CookieUtil::setCookie('zikula_mobile_theme', '1', time()+3600*24*365, '/');

        return $this->redirect(System::getHomepageUrl());
    }
    
    /**
     * Disable mobile Theme 
     *
     * @return void
     */
    public function disableMobileTheme()
    {
        CookieUtil::setCookie('zikula_mobile_theme', '2', time()+3600*24*365, '/');

        return $this->redirect(System::getHomepageUrl());
    }
}
