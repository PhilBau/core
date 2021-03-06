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

use Zikula_View;
use ModUtil;
use LogUtil;
use SecurityUtil;
use SessionUtil;
use ZLanguage;
use BlockUtil;
use Zikula_Controller_AbstractBlock;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrative controllers for the blocks module
 */
class AdminController extends \Zikula_AbstractController
{
    /**
     * Post initialise.
     *
     * @return void
     */
    protected function postInitialize()
    {
        // In this controller we do not want caching.
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);
    }

    /**
     * The main administration function.
     *
     * @return void
     */
    public function mainAction()
    {
        // Security check will be done in view()
        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * View all blocks.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit permissions to the module
     */
    public function viewAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        // get any filter form submissions
        $sfilter = SessionUtil::getVar('filter', array(), '/Blocks');
        $filter = $this->request->request->get('filter', $sfilter);
        $clear = $this->request->request->get('clear', 0);
        if ($clear) {
            $filter = array();
            SessionUtil::setVar('filter', $filter, '/Blocks');
        }

        // sort and sortdir GET parameters override filter values
        $sort = (isset($filter['sort']) && !empty($filter['sort'])) ? strtolower($filter['sort']) : 'bid';
        $sortdir = (isset($filter['sortdir']) && !empty($filter['sortdir'])) ? strtoupper($filter['sortdir']) : 'ASC';

        $filter['sort'] = $this->request->query->get('sort', $sort);
        $filter['sortdir'] = $this->request->query->get('sortdir', $sortdir);
        if ($filter['sortdir'] != 'ASC' && $filter['sortdir'] != 'DESC') {
            $filter['sortdir'] = 'ASC';
        }
        $filter['blockposition_id'] = isset($filter['blockposition_id']) ? $filter['blockposition_id'] : 0;
        $filter['module_id'] = isset($filter['module_id']) ? $filter['module_id'] : 0;
        $filter['language'] = isset($filter['language']) ? $filter['language'] : '';
        $filter['active_status'] = isset($filter['active_status']) ? $filter['active_status'] : 0;

        $this->view->assign('filter', $filter)
                   ->assign('sort', $filter['sort'])
                   ->assign('sortdir', $filter['sortdir']);

        // generate an authorisation key for the links
        $csrftoken = SecurityUtil::generateCsrfToken($this->serviceManager, true);
        $this->view->assign('csrftoken', $csrftoken);

        // Get all blocks
        $blocks = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getall', $filter);

        // get all possible block positions and build assoc array for easier usage later on
        $blockspositions = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallpositions');
        foreach ($blockspositions as $blocksposition) {
            $allbposarray[$blocksposition['pid']] = $blocksposition['name'];
        }

        // loop round each item calculating the additional information
        $blocksitems = array();
        foreach ($blocks as $key => $block) {

            $block = $block->toArray();

            // set the module that holds the block
            $modinfo = ModUtil::getInfo($block['mid']);
            $block['modname'] = $modinfo['displayname'];

            // set the block's language
            if (empty($block['language'])) {
                $block['language'] = $this->__('All');
            } else {
                $block['language'] = ZLanguage::getLanguageName($block['language']);
            }

            // set the block's position(s)
            $bposarray = array();
            $thisblockspositions = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallblockspositions', array('bid' => $block['bid']));
            foreach ($thisblockspositions as $singleblockposition) {
                $bposarray[] = $allbposarray[$singleblockposition['pid']];
            }
            $block['positions'] = implode(', ', $bposarray);
            unset($bposarray);

            // push block to array
            $blocksitems[] = $block;
        }
        $this->view->assign('blocks', $blocksitems);

        // get the block positions and assign them to the template
        $positions = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallpositions');
        $this->view->assign('positions', $positions);

        // Return the output that has been generated by this function
        return $this->view->fetch('blocks_admin_view.tpl');
    }

    /**
     * Deactivate a block.
     *
     * @param int $bid block id
     *
     * @return Response symfony response object
     */
    public function deactivateAction()
    {
        // Get parameters
        $bid = $this->request->query->get('bid');
        $csrftoken = $this->request->query->get('csrftoken');
        $this->checkCsrfToken($csrftoken);

        // Pass to API
        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'deactivate', array('bid' => $bid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Block now inactive.'));
        }

        // Redirect
        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Activate a block.
     *
     * @param int $bid block id.
     *
     * @return Response symfony response object
     */
    public function activateAction()
    {
        // Get parameters
        $bid = $this->request->query->get('bid');
        $csrftoken = $this->request->query->get('csrftoken');
        $this->checkCsrfToken($csrftoken);

        // Pass to API
        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'activate', array('bid' => $bid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Block now active.'));
        }

        // Redirect
        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Modify a block.
     *
     * @param int $bid block id.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit permissions over the block
     * @throws NotFoundHttpException Thrown if the requested block doesn't exist
     */
    public function modifyAction()
    {
        // Get parameters
        $bid = $this->request->query->get('bid');

        // Get details on current block
        $blockinfo = BlockUtil::getBlockInfo($bid);

        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', "$blockinfo[bkey]:$blockinfo[title]:$blockinfo[bid]", ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        // check the blockinfo array
        if (empty($blockinfo)) {
            throw new NotFoundHttpException($this->__('Sorry! No such block found.'));
        }

        // get the block's placements
        $placements = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallblockspositions', array('bid' => $bid));
        $placements_pids = array();
        foreach ($placements as $placement) {
            $placements_pids[] = $placement['pid'];
        }
        $blockinfo['placements'] = $placements_pids;

        // Load block
        $modinfo = ModUtil::getInfo($blockinfo['mid']);
        $blockObj = BlockUtil::load($modinfo['name'], $blockinfo['bkey']);
        if (!$blockObj) {
            throw new NotFoundHttpException($this->__('Sorry! No such block found.'));
        }

        // Title - putting a title ad the head of each page reminds the user what
        // they are doing
        if (!empty($modinfo['name'])) {
            $this->view->assign('modtitle', "$modinfo[name]/$blockinfo[bkey]");
        }

        // Add hidden block id to form
        $this->view->assign('bid', $bid);

        // assign the block values to the template
        $this->view->assign($blockinfo);

        // build and assign the list of modules
        $homepage = array('_homepage_' => $this->__('Homepage'));
        $modules  = ModUtil::getAllMods();
        unset($modules['zikula']);
        foreach ($modules as $name => $module) {
            $modules[$name] = $module['displayname'];
        }
        asort($modules);

        $this->view->assign('mods', array_merge($homepage, $modules));

        // assign block positions
        $positions = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallpositions');
        $block_positions = array();
        foreach ($positions as $position) {
            $block_positions[$position['pid']] = $position['name'];
        }
        $this->view->assign('block_positions', $block_positions);

        // Block-specific
        $blockoutput = '';
        if ($blockObj instanceof Zikula_Controller_AbstractBlock) {
            $blockoutput = call_user_func(array($blockObj, 'modify'), $blockinfo);
        } else {
            $usname = preg_replace('/ /', '_', $modinfo['name']);
            $updatefunc = $usname . '_' . $blockinfo['bkey'] . 'block_modify';
            if (function_exists($updatefunc)) {
                $blockoutput = $updatefunc($blockinfo);
            }
        }

        // Block output
        $this->view->assign('blockoutput', $blockoutput);

        // Tableless for blockoutput
        if (!isset($GLOBALS['blocks_modules'][$blockinfo['mid']][$blockinfo['bkey']]['admin_tableless'])) {
            $GLOBALS['blocks_modules'][$blockinfo['mid']][$blockinfo['bkey']]['admin_tableless'] = false;
        }

        // Requirement for the block
        if (!isset($GLOBALS['blocks_modules'][$blockinfo['mid']][$blockinfo['bkey']]['requirement'])) {
            $GLOBALS['blocks_modules'][$blockinfo['mid']][$blockinfo['bkey']]['requirement'] = '';
        }

        // Assign blockinfo to the template
        $this->view->assign($GLOBALS['blocks_modules'][$blockinfo['mid']][$blockinfo['bkey']]);

        // Refresh
        $refreshtimes = array(
            60 => $this->__('One minute'),
            120 => $this->__('Two minutes'),
            300 => $this->__('Five minutes'),
            600 => $this->__('Ten minutes'),
            900 => $this->__('Fifteen minutes'),
            1800 => $this->__('Half an hour'),
            3600 => $this->__('One hour'),
            7200 => $this->__('Two hours'),
            14400 => $this->__('Four hours'),
            43200 => $this->__('Twelve hours'),
            86400 => $this->__('One day'),
            172800 => $this->__('Two days'),
            259200 => $this->__('Three days'),
            345600 => $this->__('Four days'),
            432000 => $this->__('Five days'),
            518400 => $this->__('Six days'),
            604800 => $this->__('Seven days'));
        $this->view->assign('blockrefreshtimes', $refreshtimes);

        // Return the output that has been generated by this function
        return $this->view->fetch('blocks_admin_modify.tpl');
    }

    /**
     * Update a block.
     *
     * @param int    $bid         block id to update.
     * @param string $title       the new title of the block.
     * @param string $description the new description of the block.
     * @param array  $positions   the new position(s) of the block.
     * @param array  $modules     the modules to display the block on.
     * @param string $url         the new URL of the block.
     * @param string $language    the new language of the block.
     * @param string $content     the new content of the block.
     *
     * @throws NotFoundHttpException Thrown if the block to be updated doesn't exist
     *
     * @return void
     */
    public function updateAction()
    {
        $this->checkCsrfToken();

        // Get parameters
        $bid = $this->request->request->get('bid');
        $title = $this->request->request->get('title');
        $description = $this->request->request->get('description');
        $language = $this->request->request->get('language');
        $collapsable = $this->request->request->get('collapsable', 0);
        $defaultstate = $this->request->request->get('defaultstate', 1);
        $content = $this->request->request->get('content', '');
        $refresh = $this->request->request->get('refresh');
        $positions = $this->request->request->get('positions');
        $filter = $this->request->request->get('filters', array());
        $returntoblock = $this->request->request->get('returntoblock');

        // not stored in a block
        $redirect = $this->request->request->get('redirect', null);
        $cancel = $this->request->request->get('cancel', null);

        if (isset($cancel)) {
            if (isset($redirect) && !empty($redirect)) {
                $this->redirect(urldecode($redirect));
            }
            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
        }


        // Fix for null language
        if (!isset($language)) {
            $language = '';
        }

        // Get and update block info
        $blockinfo = BlockUtil::getBlockInfo($bid);


        $blockinfo['title'] = $title;
        $blockinfo['description'] = $description;
        $blockinfo['bid'] = $bid;
        $blockinfo['language'] = $language;
        $blockinfo['collapsable'] = $collapsable;
        $blockinfo['defaultstate'] = $defaultstate;
        $blockinfo['content'] = $content;
        $blockinfo['refresh'] = $refresh;
        $blockinfo['positions'] = $positions;
        $blockinfo['filter'] = $filter;

        // Load block
        $modinfo = ModUtil::getInfo($blockinfo['mid']);
        $blockObj = BlockUtil::load($modinfo['name'], $blockinfo['bkey']);
        if (!$blockObj) {
            throw new NotFoundHttpException($this->__('Sorry! No such block found.'));
        }

        // Do block-specific update
        if ($blockObj instanceof Zikula_Controller_AbstractBlock) {
            $blockinfo = call_user_func(array($blockObj, 'update'), $blockinfo);
        } else {
            $usname = preg_replace('/ /', '_', $modinfo['name']);
            $updatefunc = $usname . '_' . $blockinfo['bkey'] . 'block_update';
            if (function_exists($updatefunc)) {
                $blockinfo = $updatefunc($blockinfo);
            }
        }

        if (!$blockinfo) {
            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'modify', array('bid' => $bid)));
        }

        // Pass to API
        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'update', $blockinfo)) {
            // Success
            LogUtil::registerStatus($this->__('Done! Block saved.'));
        }

        if (isset($redirect) && !empty($redirect)) {
            $this->redirect(urldecode($redirect));
        }

        if (!empty($returntoblock)) {
            // load the block config again
            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'modify',
                            array('bid' => $returntoblock)));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Display form for a new block.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Throw if the user doesn't have permission to add a block
     * @throws \RuntimeException          Throw if the list of blocks cannot be loaded
     */
    public function newblockAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_ADD)) {
            throw new AccessDeniedHttpException();
        }

        // Get parameters if exists
        $default = array(
            'title' => '',
            'description' => '',
            'language' => ZLanguage::getLanguageCode(),
            'blockid' => null,
            'positions' => array(),
            'collapsable' => 0,
            'defaultstate' => 1
        );
        $inputblock = $this->request->query->get('block', $default);

        // Block
        // Load all blocks
        $blocks = BlockUtil::loadAll();
        if (!$blocks) {
            throw new \RuntimeException($this->__('Error! Could not load blocks.'));
        }

        $blockinfo = array();
        foreach ($blocks as $moduleblocks) {
            foreach ($moduleblocks as $block) {
                $modinfo = ModUtil::getInfoFromName($block['module']);
                $blockinfo[$block['mid'] . ':' . $block['bkey']] = $modinfo['displayname'] . '/' . $block['text_type_long'];
            }
        }
        $this->view->assign('blockids', $blockinfo);

        // assign block positions
        $positions = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getallpositions');
        $block_positions = array();
        foreach ($positions as $position) {
            $block_positions[$position['pid']] = $position['name'];
        }
        $this->view->assign('block_positions', $block_positions);

        // Return the output that has been generated by this function
        return $this->view->assign('block', $inputblock)
                          ->fetch('blocks_admin_newblock.tpl');
    }

    /**
     * Create a new block.
     *
     * @param string $title       the new title of the block.
     * @param string $description the new description of the block.
     * @param int    $blockid     block id to create.
     * @param string $language    the language to assign to the block.
     * @param string $position    the position of the block.
     *
     * @return void
     *
     * @throws \InvalidArgumentException Thrown if no block id is supplied
     */
    public function createAction()
    {
        $this->checkCsrfToken();

        // Get parameters
        $block = $this->request->request->get('block');

        if ($block['blockid'] == '') {
            $block['blockid'] = 'error';
            $url = ModUtil::url('ZikulaBlocksModule', 'admin', 'newblock', array('block' => $block));
            throw new \InvalidArgumentException($this->__('You must choose a block.'));
        }

        list($mid, $bkey) = explode(':', $block['blockid']);
        $block['mid']  = $mid;
        $block['bkey'] = $bkey;

        // Fix for null language
        if (!isset($block['language'])) {
            $block['language'] = '';
        }

        // Default values
        $block['collapsable']  = isset($block['collapsable']) ? $block['collapsable'] : 0;
        $block['defaultstate'] = isset($block['defaultstate']) ? $block['defaultstate'] : 1;

        // Pass to API
        $bid = ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'create', $block);

        if ($bid != false) {
            LogUtil::registerStatus($this->__('Done! Block created.'));
            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'modify', array('bid' => $bid)));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Delete a block.
     *
     * @param int bid the block id.
     * @param bool confirm to delete block.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have delete permissions over the block
     * @throws NotFoundHttpException Thrown the requested block doesn't exist
     */
    public function deleteAction()
    {
        // Get parameters
        $bid = (int)$this->request->query->get('bid', null);
        if (!$bid) {
            $bid = (int)$this->request->request->get('bid', null);
        }
        $confirmation = $this->request->request->get('confirmation');

        // Get details on current block
        $blockinfo = BlockUtil::getBlockInfo($bid);

        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', "$blockinfo[bkey]:$blockinfo[title]:$blockinfo[bid]", ACCESS_DELETE)) {
            throw new AccessDeniedHttpException();
        }

        if ($blockinfo == false) {
            throw new NotFoundHttpException($this->__('Sorry! No such block found.'));
        }

        // Check for confirmation
        if (empty($confirmation)) {
            // No confirmation yet - get one
            // get the module info
            $modinfo = ModUtil::getInfo($blockinfo['mid']);

            if (!empty($modinfo['name'])) {
                $this->view->assign('blockname', "$modinfo[name]/$blockinfo[bkey]");
            } else {
                $this->view->assign('blockname', "Core/$blockinfo[bkey]");
            }

            // add the block id
            $this->view->assign('block', $blockinfo);

            // Return the output that has been generated by this function
            return $this->view->fetch('blocks_admin_delete.tpl');
        }

        $this->checkCsrfToken();

        // Pass to API
        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'delete', array('bid' => $bid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Block deleted.'));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Display a form to create a new block position.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin access to the module
     */
    public function newpositionAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        $name = $this->request->request->get('name', '');

        // Return the output that has been generated by this function
        return $this->view->assign('name', $name)
                          ->fetch('blocks_admin_newposition.tpl');
    }

    /**
     * Display a form to create a new block position.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin access to the module
     * @throws \InvalidArgumentException Thrown if the position name is empty or not valid or
     *                                          if the position description is empty
     */
    public function createpositionAction()
    {
        $this->checkCsrfToken();

        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::position', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        // Get parameters
        $position = $this->request->request->get('position');

        // check our vars
        if (!isset($position['name']) || empty($position['name']) || !preg_match('/^[a-z0-9_-]*$/i', $position['name']) || !isset($position['description'])) {
            throw new \InvalidArgumentException(__('Invalid arguments received'));
        }

        // add the new block position
        $pid = ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'createposition', array('name' => $position['name'], 'description' => $position['description']));

        if ($pid) {
            LogUtil::registerStatus($this->__('Done! Block position created.'));
            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'modifyposition', array('pid' => $pid), null, 'blockpositionform'));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Display a form to create a new block position.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin access to the module
     */
    public function modifypositionAction()
    {
        // get our input
        $pid = $this->request->query->get('pid');

        // get the block position
        $position = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getposition', array('pid' => $pid));

        // Security check
        if (!SecurityUtil::checkPermission("ZikulaBlocksModule::$position[name]", '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        // assign the position item
        $this->view->assign('pid', $position['pid'])
                   ->assign('name', $position['name'])
                   ->assign('description', $position['description']);

        // get all blocks in the position
        $block_placements = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getblocksinposition', array('pid' => $pid));

        // get all defined blocks
        $allblocks = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getall', array('active_status' => 0));
        foreach ($allblocks as $key => $allblock) {
            $allblock = $allblock->toArray();
            // set the module that holds the block
            $modinfo = ModUtil::getInfo($allblock['mid']);
            $allblock['modname'] = $modinfo['name'];
            $allblocks[$key] = $allblock;
        }

        // loop over arrays forming a list of blocks not in the block positon and obtaining
        // full details on those that are
        $blocks = array();
        foreach ($block_placements as $blockplacement) {
            $block = BlockUtil::getBlockInfo($blockplacement['bid']);
            foreach ($allblocks as $key => $allblock) {
                if ($allblock['bid'] == $blockplacement['bid']) {
                    unset($allblocks[$key]);
                    $block['modname'] = $allblock['modname'];
                }
            }
            $blocks[] = $block;
        }

        $this->view->assign('assignedblocks', $blocks)
                   ->assign('unassignedblocks', $allblocks);

        // Return the output that has been generated by this function
        return $this->view->fetch('blocks_admin_modifyposition.tpl');
    }

    /**
     * Display a form to create a new block position.
     *
     * @return Response symfony response object
     *
     * @throws \InvalidArgumentException Thrown if the position id, name or description is not supplied
     */
    public function updatepositionAction()
    {
        $this->checkCsrfToken();

        // Get parameters
        $position = $this->request->request->get('position');

        // check our vars
        if (!isset($position['pid']) || !isset($position['name']) || !isset($position['description'])) {
            throw new \InvalidArgumentException(__('Invalid arguments received'));
        }

        // update the position
        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'updateposition',
                        array('pid' => $position['pid'], 'name' => $position['name'], 'description' => $position['description']))) {
            // all done
            LogUtil::registerStatus($this->__('Done! Block position saved.'));

            $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'modifyposition', array('pid' => $position['pid'])));
    }

    /**
     * Delete a block position.
     *
     * @param mixed[] $args {
     *      @type int  $pid          the id of the position to be deleted
     *      @type int  $objectid     generic object id maps to pid if present
     *      @type bool $confirmation confirmation that this item can be deleted
     *                       }
     *
     * @return Response|bool symfony response if confirmation is null, true if delete successful, false otherwise.
     *
     * @throws NotFoundHttpException Thrown if the position doesn't exist
     * @throws AccessDeniedHttpException Thrown if the user doesn't have permission to delete the position
     */
    public function deletepositionAction($args)
    {
        // check where to get the parameters from for this dual purpose controller
        if ($this->request->isMethod('GET')) {
            $pid = (int)$this->request->query->get('pid', null);
        } elseif ($this->request->isMethod('POST')) {
            $pid = (int)$this->request->request->get('pid', isset($args['pid']) ? $args['pid'] : null);
        }
        if ($this->request->isMethod('GET')) {
            $objectid = (int)$this->request->query->get('objectid', null);
        } elseif ($this->request->isMethod('POST')) {
            $objectid = (int)$this->request->request->get('objectid', isset($args['objectid']) ? $args['objectid'] : null);
        }
        // map the generic object id onto the category id onto the object id
        if (!empty($objectid)) {
            $pid = $objectid;
        }

        // confirmation can only come from a form so use post only here
        $confirmation = $this->request->request->get('confirmation', null);

        $item = ModUtil::apiFunc('ZikulaBlocksModule', 'user', 'getposition', array('pid' => $pid));

        if ($item == false) {
            throw new NotFoundHttpException($this->__('Error! No such block position found.'));
        }

        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::position', "$item[name]::$pid", ACCESS_DELETE)) {
            throw new AccessDeniedHttpException();
        }

        // Check for confirmation.
        if (empty($confirmation)) {
            // No confirmation yet
            $this->view->assign('position', $item);

            return $this->view->fetch('blocks_admin_deleteposition.tpl');
        }

        $this->checkCsrfToken();

        if (ModUtil::apiFunc('ZikulaBlocksModule', 'admin', 'deleteposition', array('pid' => $pid))) {
            // Success
            LogUtil::registerStatus($this->__('Done! Block position deleted.'));
        }

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }

    /**
     * Any config options would likely go here in the future.
     *
     * @return Response symfony response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin access to the module
     */
    public function modifyconfigAction()
    {
        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        // assign all the module vars
        $this->view->assign($this->getVars());

        // Return the output that has been generated by this function
        return $this->view->fetch('blocks_admin_modifyconfig.tpl');
    }

    /**
     * Set config variable(s).
     *
     * @return void
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have admin access to the module
     */
    public function updateconfigAction()
    {
        $this->checkCsrfToken();

        // Security check
        if (!SecurityUtil::checkPermission('ZikulaBlocksModule::', '::', ACCESS_ADMIN)) {
            throw new AccessDeniedHttpException();
        }

        $collapseable = $this->request->request->get('collapseable');

        if (!isset($collapseable) || !is_numeric($collapseable)) {
            $collapseable = 0;
        }

        $this->setVar('collapseable', $collapseable);

        // the module configuration has been updated successfuly
        LogUtil::registerStatus($this->__('Done! Saved module configuration.'));

        $this->redirect(ModUtil::url('ZikulaBlocksModule', 'admin', 'view'));
    }
}
