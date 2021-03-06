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

namespace Zikula\Module\ExtensionsModule\Listener;

use Zikula_View;
use LogUtil;
use SecurityUtil;
use HookUtil;
use EventUtil;
use Zikula\Core\Event\GenericEvent;
use Zikula\Module\ExtensionsModule\Util;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * HooksUI class.
 */
class HookUiListener
{
    /**
     * Display hooks user interface
     *
     * @param Zikula\Core\Event\GenericEvent $event
     *
     * @return void
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin permissions over the module
     */
    public static function hooks(GenericEvent $event)
    {
        // check if this is for this handler
        $subject = $event->getSubject();
        if ($event['method'] != 'hooks' || !(strrpos(get_class($subject), '_Controller_Admin') ||
                                              strrpos(get_class($subject), '\\AdminController'))) {
           return;
        }

        // get view
        $view = Zikula_View::getInstance('ZikulaExtensionsModule', false);

        // get module's name and assign it to template
        $moduleName = $subject->getName();
        $view->assign('currentmodule', $moduleName);

        // check if user has admin permission on this module
        if (!SecurityUtil::checkPermission($moduleName.'::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        // create an instance of the module's version
        // we will use it to get the bundles
        $moduleVersionObj = Util::getVersionMeta($moduleName);

        // find out the capabilities of the module
        $isProvider = (HookUtil::isProviderCapable($moduleName)) ? true : false;
        $view->assign('isProvider', $isProvider);

        $isSubscriber = (HookUtil::isSubscriberCapable($moduleName)) ? true : false;
        $view->assign('isSubscriber', $isSubscriber);

        $isSubscriberSelfCapable = (HookUtil::isSubscriberSelfCapable($moduleName)) ? true : false;
        $view->assign('isSubscriberSelfCapable', $isSubscriberSelfCapable);

        // get areas of module and bundle titles also
        if ($isProvider) {
            $providerAreas = HookUtil::getProviderAreasByOwner($moduleName);
            $view->assign('providerAreas', $providerAreas);

            $providerAreasToTitles = array();
            foreach ($providerAreas as $providerArea) {
                $providerAreasToTitles[$providerArea] = $view->__($moduleVersionObj->getHookProviderBundle($providerArea)->getTitle());
            }
            $view->assign('providerAreasToTitles', $providerAreasToTitles);
        }

        if ($isSubscriber) {
            $subscriberAreas = HookUtil::getSubscriberAreasByOwner($moduleName);
            $view->assign('subscriberAreas', $subscriberAreas);

            $subscriberAreasToTitles = array();
            foreach ($subscriberAreas as $subscriberArea) {
                $subscriberAreasToTitles[$subscriberArea] = $view->__($moduleVersionObj->getHookSubscriberBundle($subscriberArea)->getTitle());
            }
            $view->assign('subscriberAreasToTitles', $subscriberAreasToTitles);

            $subscriberAreasToCategories = array();
            foreach ($subscriberAreas as $subscriberArea) {
                $category = $view->__($moduleVersionObj->getHookSubscriberBundle($subscriberArea)->getCategory());
                $subscriberAreasToCategories[$subscriberArea] = $category;
            }
            $view->assign('subscriberAreasToCategories', $subscriberAreasToCategories);

            $subscriberAreasAndCategories = array();
            foreach ($subscriberAreas as $subscriberArea) {
                $category = $view->__($moduleVersionObj->getHookSubscriberBundle($subscriberArea)->getCategory());
                $subscriberAreasAndCategories[$category][] = $subscriberArea;
            }
            $view->assign('subscriberAreasAndCategories', $subscriberAreasAndCategories);
        }

        // get available subscribers that can attach to provider
        if ($isProvider && !empty($providerAreas)) {
            $hooksubscribers = HookUtil::getHookSubscribers();
            $total_hooksubscribers = count($hooksubscribers);
            $total_available_subscriber_areas = 0;
            for ($i=0 ; $i < $total_hooksubscribers ; $i++) {
                // don't allow subscriber and provider to be the same
                // unless subscriber has the ability to connect to it's own providers
                if ($hooksubscribers[$i]['name'] == $moduleName) {
                    unset($hooksubscribers[$i]);
                    continue;
                }
                // does the user have admin permissions on the subscriber module?
                if (!SecurityUtil::checkPermission($hooksubscribers[$i]['name']."::", '::', ACCESS_ADMIN)) {
                    unset($hooksubscribers[$i]);
                    continue;
                }

                // create an instance of the subscriber's version
                $hooksubscriberVersionObj = Util::getVersionMeta($hooksubscribers[$i]['name']);

                // get the areas of the subscriber
                $hooksubscriberAreas = HookUtil::getSubscriberAreasByOwner($hooksubscribers[$i]['name']);
                $hooksubscribers[$i]['areas'] = $hooksubscriberAreas;
                $total_available_subscriber_areas += count($hooksubscriberAreas);

                // and get the titles
                $hooksubscriberAreasToTitles = array();
                foreach ($hooksubscriberAreas as $hooksubscriberArea) {
                    $hooksubscriberAreasToTitles[$hooksubscriberArea] = $view->__($hooksubscriberVersionObj->getHookSubscriberBundle($hooksubscriberArea)->getTitle());
                }
                $hooksubscribers[$i]['areasToTitles'] = $hooksubscriberAreasToTitles;

                // and get the categories
                $hooksubscriberAreasToCategories = array();
                foreach ($hooksubscriberAreas as $hooksubscriberArea) {
                    $category = $view->__($hooksubscriberVersionObj->getHookSubscriberBundle($hooksubscriberArea)->getCategory());
                    $hooksubscriberAreasToCategories[$hooksubscriberArea] = $category;
                }
                $hooksubscribers[$i]['areasToCategories'] = $hooksubscriberAreasToCategories;
            }
            $view->assign('hooksubscribers', $hooksubscribers);
            $view->assign('total_available_subscriber_areas', $total_available_subscriber_areas);
        } else {
            $view->assign('total_available_subscriber_areas',0);
        }

        // get providers that are already attached to the subscriber
        // and providers that can attach to the subscriber
        if ($isSubscriber && !empty($subscriberAreas)) {
            // get current sorting
            $currentSortingTitles = array();
            $currentSorting = array();
            $total_attached_provider_areas = 0;
            for ($i=0 ; $i < count($subscriberAreas) ; $i++) {
                $sortsByArea = HookUtil::getBindingsFor($subscriberAreas[$i]);
                foreach ($sortsByArea as $sba) {
                    $areaname = $sba['areaname'];
                    $category = $sba['category'];

                    if (!isset($currentSorting[$category])) {
                        $currentSorting[$category] = array();
                    }

                    if (!isset($currentSorting[$category][$subscriberAreas[$i]])) {
                        $currentSorting[$category][$subscriberAreas[$i]] = array();
                    }

                    array_push($currentSorting[$category][$subscriberAreas[$i]], $areaname);
                    $total_attached_provider_areas++;

                    // get hook provider from it's area
                    $sbaProviderModule = HookUtil::getOwnerByArea($areaname);

                    // create an instance of the provider's version
                    $sbaProviderModuleVersionObj = Util::getVersionMeta($sbaProviderModule);

                    // get the bundle title
                    $currentSortingTitles[$areaname] = $view->__($sbaProviderModuleVersionObj->getHookProviderBundle($areaname)->getTitle());
                }
            }
            $view->assign('areasSorting', $currentSorting);
            $view->assign('areasSortingTitles', $currentSortingTitles);
            $view->assign('total_attached_provider_areas', $total_attached_provider_areas);

            // get available providers
            $hookproviders = HookUtil::getHookProviders();
            $total_hookproviders = count($hookproviders);
            $total_available_provider_areas = 0;
            for ($i=0 ; $i < $total_hookproviders ; $i++) {
                // don't allow subscriber and provider to be the same
                // unless subscriber has the ability to connect to it's own providers
                if ($hookproviders[$i]['name'] == $moduleName && !$isSubscriberSelfCapable) {
                    unset($hookproviders[$i]);
                    continue;
                }

                // does the user have admin permissions on the provider module?
                if (!SecurityUtil::checkPermission($hookproviders[$i]['name']."::", '::', ACCESS_ADMIN)) {
                    unset($hookproviders[$i]);
                    continue;
                }

                // create an instance of the provider's version
                $hookproviderVersionObj = Util::getVersionMeta($hookproviders[$i]['name']);

                // get the areas of the provider
                $hookproviderAreas = HookUtil::getProviderAreasByOwner($hookproviders[$i]['name']);
                $hookproviders[$i]['areas'] = $hookproviderAreas;
                $total_available_provider_areas += count($hookproviderAreas);

                // and get the titles
                $hookproviderAreasToTitles = array();
                foreach ($hookproviderAreas as $hookproviderArea) {
                    $hookproviderAreasToTitles[$hookproviderArea] = $view->__($hookproviderVersionObj->getHookProviderBundle($hookproviderArea)->getTitle());
                }
                $hookproviders[$i]['areasToTitles'] = $hookproviderAreasToTitles;

                // and get the categories
                $hookproviderAreasToCategories = array();
                foreach ($hookproviderAreas as $hookproviderArea) {
                    $hookproviderAreasToCategories[$hookproviderArea] = $view->__($hookproviderVersionObj->getHookProviderBundle($hookproviderArea)->getCategory());
                }
                $hookproviders[$i]['areasToCategories'] = $hookproviderAreasToCategories;

                // and build array with category => areas
                $hookproviderAreasAndCategories = array();
                foreach ($hookproviderAreas as $hookproviderArea) {
                    $category = $view->__($hookproviderVersionObj->getHookProviderBundle($hookproviderArea)->getCategory());
                    $hookproviderAreasAndCategories[$category][] = $hookproviderArea;
                }
                $hookproviders[$i]['areasAndCategories'] = $hookproviderAreasAndCategories;
            }
            $view->assign('hookproviders', $hookproviders);
            $view->assign('total_available_provider_areas', $total_available_provider_areas);
        } else {
            $view->assign('hookproviders', array());
        }

        $event->setData($view->fetch('Admin/HookUi/hooks.tpl'));
        $event->stopPropagation();
    }

    /**
     * Display services availble to the module
     *
     * @param Zikula\Core\Event\GenericEvent $event
     *
     * @return void
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin permissions over the module
     */
    public static function moduleservices(GenericEvent $event)
    {
        // check if this is for this handler
        $subject = $event->getSubject();
        if (!($event['method'] == 'moduleservices' && (strrpos(get_class($subject), '_Controller_Admin') ||
                                                       strrpos(get_class($subject), '\\AdminController')))) {
           return;
        }

        $moduleName = $subject->getName();
        if (!SecurityUtil::checkPermission($moduleName.'::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        $view = Zikula_View::getInstance('ZikulaExtensionsModule', false);
        $view->assign('currentmodule', $moduleName);

        // notify EVENT here to gather any system service links
        $localevent = new GenericEvent($subject, array('modname' => $moduleName));
        EventUtil::dispatch('module_dispatch.service_links', $localevent);
        $sublinks = $localevent->getData();
        $view->assign('sublinks', $sublinks);

        $event->setData($view->fetch('Admin/HookUi/moduleservices.tpl'));
        $event->stopPropagation();
    }
}
