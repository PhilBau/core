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

namespace Zikula\Module\UsersModule\Listener;

use Zikula\Module\UsersModule\Constant as UsersConstant;
use ServiceUtil;

/**
 * Persistent event listener used to clean up the Users module session variables related to logging in.
 */
class ClearUsersNamespaceListener
{
    /**
     * The module name.
     *
     * @var string
     */
    protected static $modname = UsersConstant::MODNAME;

    /**
     * Clears the session variable namespace used by the Users module.
     * Triggered by the 'user.logout.succeeded' and 'frontcontroller.exception' events.
     * This is to ensure no leakage of authentication information across sessions or between critical
     * errors. This prevents, for example, the login process from becoming confused about its state
     * if it detects session variables containing authentication information which might make it think
     * that a re-attempt is in progress.
     *
     * @param \Zikula_Event $event The event that triggered this handler.
     *
     * @return void
     */
    public static function clearUsersNamespaceListener(\Zikula_Event $event)
    {
        $eventName = $event->getName();
        $modinfo = $event->hasArgument('modinfo') ? $event->getArgument('modinfo') : array();

        $doClear = ($eventName == 'user.logout.succeeded') || (($eventName == 'frontcontroller.exception')
            && isset($modinfo) && is_array($modinfo) && !empty($modinfo) && !isset($modinfo['name']) && ($modinfo['name'] == self::$modname));

        if ($doClear) {
            $serviceManager = ServiceUtil::getManager();
            $session = $serviceManager->get('session');
            $session->clearNamespace(UsersConstant::SESSION_VAR_NAMESPACE);
            //Do not setNotified. Not handling the exception, just reacting to it.
        }
    }
}
