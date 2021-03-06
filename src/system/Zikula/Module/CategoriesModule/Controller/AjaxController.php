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

namespace Zikula\Module\CategoriesModule\Controller;

use SecurityUtil;
use CategoryUtil;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Zikula\Core\Response\Ajax\AjaxResponse;
use FormUtil;
use ZLanguage;
use DBObject;
use Zikula\Core\Response\Ajax\BadDataResponse;
use Zikula_View;
use Zikula\Module\CategoriesModuleGenericUtil;

/**
 * Ajax controllers for the categories module
 */
class AjaxController extends \Zikula_Controller_AbstractAjax
{
    /**
     * Resequence categories
     *
     * @return void
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit access to the module
     */
    public function resequenceAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        $data  = json_decode($this->request->request->get('data'), true);
        $cats = CategoryUtil::getSubCategories(1, true, true, true, true, true, '', 'id');

        foreach ($cats as $k => $cat) {
            $cid = $cat['id'];
            if (isset($data[$cid])) {
                $category = $this->entityManager->find('Zikula\Module\CategoriesModule\Entity\CategoryEntity', $cid);
                $category['sort_value'] = $data[$cid]['lineno'];
                $category['parent'] = $this->entityManager->getReference('Zikula\Module\CategoriesModule\Entity\CategoryEntity', $data[$cid]['parent']);
            }
        }

        $this->entityManager->flush();

        $result = array(
            'response' => true
        );

    }

    /**
     * Edit a category
     *
     * @param mixed[] $args {
     *      @type string $mode   the mode of operation (new or edit)
     *      @type int    $cid    the category id
     *      @type int    $parent the parent category id
     *                       }
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit or add (depending mode) access to the module
     * @throws NotFoundHttpException Thrown if category cannot be found
     */
    public function editAction($args = array())
    {
        $this->checkAjaxToken();

        $mode = isset($args['mode']) ? $args['mode'] : $this->request->request->get('mode', 'new');
        $accessLevel = $mode == 'edit' ? ACCESS_EDIT : ACCESS_ADD;
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', $accessLevel)) {
            throw new AccessDeniedHttpException();
        }

        $cid = isset($args['cid']) ? $args['cid'] : $this->request->request->get('cid', 0);
        $parent = isset($args['parent']) ? $args['parent'] : $this->request->request->get('parent', 1);
        $validationErrors = FormUtil::getValidationErrors();
        $editCat = '';

        $languages = ZLanguage::getInstalledLanguages();

        // indicates that we're editing
        if ($mode == 'edit') {
            if (!$cid) {
                return new BadDataResponse($this->__('Error! Cannot determine valid \'cid\' for edit mode in \'Categories_admin_edit\'.'));
            }
            $editCat = CategoryUtil::getCategoryByID($cid);
            if (!$editCat) {
                throw new NotFoundHttpException($this->__('Sorry! No such item found.'));
            }
        } else {
            // someone just pressed 'new' -> populate defaults
            $editCat['sort_value'] = '0';
            $editCat['parent_id'] = $parent;
        }

        $attributes = isset($editCat['__ATTRIBUTES__']) ? $editCat['__ATTRIBUTES__'] : array();

        $this->setView();
        $this->view->setCaching(Zikula_View::CACHE_DISABLED);

        $this->view->assign('mode', $mode)
                   ->assign('category', $editCat)
                   ->assign('attributes', $attributes)
                   ->assign('languages', $languages);

        $result = array(
            'action' => $mode == 'new' ? 'add' : 'edit',
            'result' => $this->view->fetch('Ajax/edit.tpl'),
            'validationErrors' => $validationErrors
        );

        if ($validationErrors) {
            return new BadDataResponse($validationErrors, $result);
        }
        return new AjaxResponse($result);
    }

    /**
     * Copy a category
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have add access to the module
     */
    public function copyAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_ADD)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');
        $parent = $this->request->request->get('parent');

        $cat = CategoryUtil::getCategoryByID($cid);
        CategoryUtil::copyCategoriesByPath($cat['ipath'], $parent);

        $copyParent = CategoryUtil::getCategoryByID($cat['parent_id']);

        $categories = CategoryUtil::getSubCategories($copyParent['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $copyParent['parent_id'],
            'withWraper' => false,
        );

        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }
        $result = array(
            'action' => 'copy',
            'cid' => $cid,
            'copycid' => $copyParent['id'],
            'parent' => $copyParent['parent_id'],
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );
        return new AjaxResponse($result);
    }

    /**
     * Delete a category
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have delete access to the module
     */
    public function deleteAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');
        $cat = CategoryUtil::getCategoryByID($cid);

        CategoryUtil::deleteCategoriesByPath($cat['ipath']);

        $result = array(
            'action' => 'delete',
            'cid' => $cid,
            'result' => true
        );
        return new AjaxResponse($result);
    }

    /**
     * Delete a category and move any existing subcategories
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have delete access to the module
     */
    public function deleteandmovesubsAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');
        $parent = $this->request->request->get('parent');

        $cat = CategoryUtil::getCategoryByID($cid);

        CategoryUtil::moveSubCategoriesByPath($cat['ipath'], $parent);
        CategoryUtil::deleteCategoryByID($cat['id']);

        // need to re-render new parents node
        $newParent = CategoryUtil::getCategoryByID($parent);

        $categories = CategoryUtil::getSubCategories($newParent['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $newParent['parent_id'],
            'withWraper' => false,
        );
        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }

        $result = array(
            'action' => 'deleteandmovesubs',
            'cid' => $cid,
            'parent' => $newParent['id'],
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );
        return new AjaxResponse($result);
    }

    /**
     * Display a dialog to get the category to move subcategories to once the parent has been deleted
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have delete access to the module
     */
    public function deletedialogAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_DELETE)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');

        $allCats = CategoryUtil::getSubCategories(1, true, true, true, false, true, $cid);
        $selector = CategoryUtil::getSelector_Categories($allCats);

        $this->setView();
        $this->view->setCaching(\Zikula_View::CACHE_DISABLED);

        $this->view->assign('categorySelector', $selector);
        $result = array(
            'result' => $this->view->fetch('Ajax/delete.tpl'),
        );

        return new AjaxResponse($result);
    }

    /**
     * Activate a category
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit access to the module
     */
    public function activateAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');
        $cat = $this->entityManager->find('Zikula\Module\CategoriesModule\Entity\CategoryRegistryEntity', $cid);
        $cat['status'] = 'A';
        $this->entityManager->flush();

        $result = array(
            'action' => 'activate',
            'cid' => $cid,
            'result' => true
        );
        return new AjaxResponse($result);
    }

    /**
     * Deactivate a category
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit access to the module
     */
    public function deactivateAction()
    {
        $this->checkAjaxToken();
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', ACCESS_EDIT)) {
            throw new AccessDeniedHttpException();
        }

        $cid = $this->request->request->get('cid');
        $cat = $this->entityManager->find('Zikula\Module\CategoriesModule\Entity\CategoryRegistryEntity', $cid);
        $cat['status'] = 'I';
        $this->entityManager->flush();

        $result = array(
            'action' => 'deactivate',
            'cid' => $cid,
            'result' => true
        );
        return new AjaxResponse($result);
    }

    /**
     * Save a category
     *
     * @return AjaxResponse ajax response object
     *
     * @throws AccessDeniedHttpException Thrown if the user doesn't have edit or add (depending on mode) access to the module
     */
    public function saveAction()
    {
        $this->checkAjaxToken();

        $mode = $this->request->request->get('mode', 'new');
        $accessLevel = $mode == 'edit' ? ACCESS_EDIT : ACCESS_ADD;
        if (!SecurityUtil::checkPermission('ZikulaCategoriesModule::', '::', $accessLevel)) {
            throw new AccessDeniedHttpException();
        }

        // get data from post
        $data = $this->request->request->get('category', null);

        if (!isset($data['is_locked'])) {
            $data['is_locked'] = 0;
        }
        if (!isset($data['is_leaf'])) {
            $data['is_leaf'] = 0;
        }
        if (!isset($data['status'])) {
            $data['status'] = 'I';
        }

        $valid = GenericUtil::validateCategoryData($data);
        if (!$valid) {
            $args = array(
                'cid' => (isset($data['cid']) ? $data['cid'] : 0),
                'parent' => $data['parent_id'],
                'mode' => $mode
            );
            return $this->editAction($args);
        }

        // process name
        $data['name'] = GenericUtil::processCategoryName($data['name']);

        // process parent
        $data['parent'] = GenericUtil::processCategoryParent($data['parent_id']);
        unset($data['parent_id']);

        // process display names
        $data['display_name'] = GenericUtil::processCategoryDisplayName($data['display_name'], $data['name']);

        // save category
        if ($mode == 'edit') {
            $category = $this->entityManager->find('Zikula\Module\CategoriesModule\Entity\CategoryEntity', $data['id']);
        } else {
            $category = new \Zikula\Module\CategoriesModule\Entity\CategoryEntity;
        }
        $prevCategoryName = $category['name'];
        $category->merge($data);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        // process path and ipath
        $category['path'] = GenericUtil::processCategoryPath($data['parent']['path'], $category['name']);
        $category['ipath'] = GenericUtil::processCategoryIPath($data['parent']['ipath'], $category['id']);

        // process category attributes
        $attrib_names = $this->request->request->get('attribute_name', array());
        $attrib_values = $this->request->request->get('attribute_value', array());
        GenericUtil::processCategoryAttributes($category, $attrib_names, $attrib_values);

        $this->entityManager->flush();

        // since a name change will change the object path, we must rebuild it here
        if ($prevCategoryName != $category['name']) {
            CategoryUtil::rebuildPaths('path', 'name', $category['id']);
        }

        $categories = CategoryUtil::getSubCategories($category['id'], true, true, true, true, true);
        $options = array(
            'nullParent' => $category['parent']->getId(),
            'withWraper' => false,
        );
        $node = CategoryUtil::getCategoryTreeJS((array)$categories, true, true, $options);

        $leafStatus = array(
            'leaf' => array(),
            'noleaf' => array()
        );
        foreach ($categories as $c) {
            if ($c['is_leaf']) {
                $leafStatus['leaf'][] = $c['id'];
            } else {
                $leafStatus['noleaf'][] = $c['id'];
            }
        }

        $result = array(
            'action' => $mode == 'edit' ? 'edit' : 'add',
            'cid' => $category['id'],
            'parent' => $category['parent']->getId(),
            'node' => $node,
            'leafstatus' => $leafStatus,
            'result' => true
        );

        return new AjaxResponse($result);
    }
}
