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

namespace Zikula\Module\BlocksModule\Controller;

use UserUtil;
use BlockUtil;
use SecurityUtil;
use System;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * User controllers for the blocks module
 */
class UserController extends \Zikula_AbstractController
{

    /**
     * The main blocks user function.
     *
     * @throws NotFoundHttpException Thrown when accessed to indicate this function isn't valid
     * @return void
     */
    public function mainAction()
    {
        throw new NotFoundHttpException(__('Sorry! This module is not designed or is not currently configured to be accessed in the way you attempted.'));
    }

    /**
     * Display a block if is active
     *
     * @param mixed[] $args {
     *      @type int  $bid          The id of the block
     *      @type bool $showinactive Override active status of block
     *                       }
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Throw if the user doesn't have edit permissions to the module
     */
    public function displayAction($args)
    {
        // Block Id - if passed - display the block
        // check both post and get
        $bid = (int)$this->request->query->get('bid', null);
        if (!$bid) {
            $bid = (int)$this->request->request->get('bid', isset($args['bid']) ? $args['bid'] : null);
        }
        $showinactive = (int)$this->request->query->get('showinactive', null);
        if (!$showinactive) {
            $showinactive = (int)$this->request->request->get('showinactive', isset($args['showinactive']) ? $args['showinactive'] : null);
        }

        // Security check for $showinactive only
        if ($showinactive && !SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        if ($bid > 0) {
            // {block} function in template is not checking for active status, so let's check here
            $blockinfo = BlockUtil::getBlockInfo($bid);
            if ($blockinfo['active'] || $showinactive) {
                $this->view->assign('args', $args);
                $this->view->assign('bid', $bid);

                return $this->view->fetch('blocks_user_display.tpl');
            }
        }

        return '';
    }

    /**
     * Change the status of a block.
     *
     * Invert the status of a given block id (collapsed/uncollapsed).
     *
     * @return void
     */
    public function changestatusAction()
    {
        $bid = $this->request->query->get('bid');
        $uid = UserUtil::getVar('uid');

        $entity = 'Zikula\Module\BlocksModule\Entity\UserBlockEntity';
        $item = $this->entityManager->getRepository($entity)->findOneBy(array('uid' => $uid, 'bid' => $bid));

        if ($item['active'] == 1) {
            $item['active'] = 0;
        } else {
            $item['active'] = 1;
        }

        $this->entityManager->flush();

        // now lets get back to where we came from
        $this->redirect(System::serverGetVar('HTTP_REFERER'));
    }
}