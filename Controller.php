<?php
/**
  *
 * PluginMarketplace
 *
 * Copyright (c) 2012-2013, Christian Suenkel <info@suenkel.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in
 *   the documentation and/or other materials provided with the
 *   distribution.
 *
 * * Neither the name of Christian Suenkel nor the names of his
 *   contributors may be used to endorse or promote products derived
 *   from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Christian Suenkel <christian@suenkel.de>
 * @link http://plugin.suenkel.org
 * @copyright 2012-2013 Christian Suenkel <info@suenkel.de>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 * @category Piwik_Plugins
 * @package  PluginMarketplace
 */
require_once dirname(__FILE__) . '/lib/Appstore.php';
require_once dirname(__FILE__) . '/lib/Manager.php';
require_once dirname(__FILE__) . '/lib/Prerequisite.php';
require_once dirname(__FILE__) . '/lib/Task/TaskSetFactory.php';
require_once dirname(__FILE__) . '/lib/Task/ProcessorFactory.php';

/**
 * Webfrontend-Controller
 *
 * @package PluginMarketplace
 * @subpackage controller
 */
class Piwik_PluginMarketplace_Controller extends Piwik_Controller_Admin
{
    /**
     * Appstore-Connector
     *
     * @var PluginMarketplace_Appstore
     */
    protected $appstore;
    
    /**
     * Session
     *
     * @var Piwik_Session_Namespace
     */
    protected $session = null;
    
    /**
     * Unique ID wich wil be generated for each instance of a piwik-installation
     *
     * @var string
     */
    protected $uid = null;
    
    /**
     * Selected release (developer, unittest, alpha, beta, stable, all)
     *
     * @var string
     */
    protected $release = null;
    
    /**
     * Falg to enable additional expert-features such as "remove"plugin
     *
     * @var boolean
     */
    protected $expert = false;
    
    /**
     *
     * @var PluginMarketplace_Manager
     */
    protected $manager = null;

    /**
     * Landing Page:
     * this page display the jquery-tabbed sections:
     * - Browse Plugins
     * - Installmanager
     * - Advanced Section
     * - Feedback
     */
    public function index()
    {
        $this->beforeAction();
        // check all requirements, if the plugin is able to do its work
        try {
            PluginMarketplace_Prerequisite::getInstance()->all();
        } catch (Exception $e) {
            // TODO: handle the Exception gracefully and display proper error-descriptions
            throw $e;
        }
        
        // cache loeschen
        Marketplace_ProcessorFactory::toCache(null);
        
        $view = Piwik_View::factory('index');
        if (!Piwik_Config::getInstance()->isFileWritable()) {
            $view->configFileNotWritable = true;
        }
        $this->render($view);
        return;
    }

    public function addprocess()
    {
        $this->beforeAction();
        $installPlugins = Piwik_Common::getRequestVar('pluginstall', array(), 'array');
        $activatePlugins = Piwik_Common::getRequestVar('plugactivate', array(), 'array');
        $removePlugins = Piwik_Common::getRequestVar('plugremove', array(), 'array');
        
        $proc = Marketplace_ProcessorFactory::newInstance();
        
        if (!empty($installPlugins)) {
            foreach ($installPlugins as $webId) {
                Marketplace_TaskSetFactory::AppstoreInstall($webId, $proc);
            }
        }
        if (!empty($activatePlugins)) {
            foreach ($activatePlugins as $pluginName) {
                Marketplace_TaskSetFactory::Activate($pluginName, $proc);
            }
        }
        if (!empty($removePlugins)) {
            foreach ($removePlugins as $pluginName) {
                Marketplace_TaskSetFactory::Remove($pluginName, $proc);
            }
        }
        Marketplace_ProcessorFactory::toCache($proc);
        $newUrl = sprintf(
                'index.php?module=PluginMarketplace&action=process&idSite=%d&period=%s&date=%s', 
                Piwik_Common::getRequestVar('idSite', 1, 'integer'), 
                Piwik_Common::getRequestVar('period', 'today', 'string'), 
                Piwik_Common::getRequestVar('date', 'day', 'string'));
        Piwik_Url::redirectToUrl($newUrl);
        exit();
    }

    /**
     * Rss-Feed widget
     */
    public function rss()
    {
        $appstore = new PluginMarketplace_Appstore();
        
        $view = Piwik_View::factory('rsswidget');
        $view->error = false;
        $rss = array();
        try {
            $rss = $appstore->getRss();
        } catch (Exception $e) {
            $view->error = $e->getMessage();
        }
        $view->rss = $rss;
        print $view->render();
    }

    /**
     * Display the (ajaxloaded) table of installed Plugins
     */
    public function tablist()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('tablist');
        $plugins = $this->manager->setRelease($this->release)
            ->getCurrentPlugins();
        $view->pluginsName = $plugins;
        $view->debug = print_r($plugins, true);
        $this->render($view);
    }

    /**
     * Display the (ajaxloaded) table of installed Plugins
     */
    public function tabexpert()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('tabexpert');
        $plugins = $this->manager->setRelease($this->release)
            ->getCurrentPlugins();
        $view->pluginsName = $plugins;
        $view->debug = print_r($plugins, true);
        $this->render($view);
    }

    /**
     * Ajax called by the "advanced"-tab
     * select the release to be used while displaying
     * (stable, alpha, beta, developer, unittest, all)
     */
    public function config()
    {
        $this->beforeAction();
        $release = Piwik_Common::getRequestVar('release', -1, 'string');
        $expert = Piwik_Common::getRequestVar('expert', -1, 'integer');
        
        if ($release !== -1) {
            if (!in_array($release, 
                    array('all', 'unittest', 'developer', 'alpha', 'beta', 'stable'))) {
                $release = 'all'; // default
            }
            $this->session->release = $release;
        }
        if ($expert !== -1) {
            $this->session->expert = ($expert == 1);
        }
        $this->replyJson(true, 
                array('release' => $this->session->release, 'expert' => $this->session->expert));
    }

    /**
     * Display the (ajaxloaded) Feedback
     */
    public function tabfeedback()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('tabfeedback');
        // Try to catch emailadress for feedback prefilled
        $view->userEmail = '';
        try {
            if (Piwik::isUserIsSuperUser()) {
                $view->userEmail = urlencode(Piwik::getSuperUserEmail());
            } else {
                $userLogin = Piwik::getCurrentUserLogin();
                $user = Piwik_UsersManager_API::getInstance()->getUser($userLogin);
                $view->userEmail = urlencode($user['email']);
            }
        } catch (Exception $e) {
        }
        $this->render($view);
    }

    /**
     * Upload a zipFiled Plugin to be deployed
     */
    public function upload()
    {
        $this->beforeAction();
        // if there is still a backendprocess is running-> display its status
        
        $view = Piwik_View::factory('upload');
        if (empty($_FILES)) {
            // display upload form
            $this->render($view);
            return;
        }
        /*
         * small precheck of uploaded file
         */
        if ($_FILES['userfile']['type'] != "application/x-zip-compressed") {
            $view->error = Piwik_Translate('APUA_Upload_error_ziponly');
            $this->render($view);
            return;
        }
        if ($_FILES['userfile']['size'] < 10 && $_FILES['userfile']['error'] == 4) {
            $view->error = Piwik_Translate('APUA_Upload_error_nofile');
            $this->render($view);
            return;
        }
        if ($_FILES['userfile']['error'] !== 0) {
            $view->error = Piwik_Translate('APUA_Upload_error_unknown');
            $this->render($view);
        }
        $proc = new Marketplace_Processor();
        Marketplace_TaskSetFactory::Upload($_FILES['userfile']['tmp_name'], $proc);
        $this->runProcess($proc);
    }

    /**
     * Display install-Status of a running backend process
     * this page polls via ajax (action step) the current status and display its progress
     */
    public function process()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('process');
        $view->plugstatus = json_encode($this->mapStatus());
        $this->render($view);
    }

    public function step()
    {
        $this->initLongrun();
        if ($proc = Marketplace_ProcessorFactory::fromCache()) {
            $somethingtoDo = $proc->step();
            Marketplace_ProcessorFactory::toCache($proc);
            $this->replyJson(true, $this->mapStatus($proc));
            return;
        }
        $this->replyJson(false, null, 'cache invalid');
    }

    /**
     * Translate the current internal status of the backendprocess to the webfront-api-format
     *
     * @return array
     */
    protected function mapStatus(Marketplace_Processor $proc = null)
    {
        if (null == $proc) {
            $proc = Marketplace_ProcessorFactory::fromCache();
        }
        if (null == $proc) {
            $proc = Marketplace_ProcessorFactory::newInstance();
        }
        
        $status = $proc->getStatus();
        // "translate" the taskname and the exception to locales
        if (isset($status['items'])) {
            foreach ($status['items'] as $id => $s) {
                $locaKey = 'PluginMarketplace_Task' . $s['task'];
                $translation = Piwik_Translate($locaKey);
                if ($translation !== $locaKey) {
                    $status['items'][$id]['task'] = $translation;
                }
                if (!empty($s['error'])) {
                    $status['items'][$id]['locaerror'] = Piwik_Translate(
                            'APUA_taskerror' . $s['error']);
                }
            }
        }
        return $status;
    }

    /**
     * helper function to reply a message to the frontend as json-encoded ajax response
     *
     * @param boolean $success
     *            - if the RMC was successfull
     * @param mixed $payload
     *            - return value of RMC
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function replyJson($success, $payload = null, $failReason = '')
    {
        $msg = array('success' => $success, 'payload' => $payload);
        $success === false && $msg['reason'] = $failReason;
        $msg = json_encode($msg);
        
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        
        ob_start();
        print $msg;
        $size = ob_get_length();
        // send headers to tell the browser to close the connection
        header('Content-type: application/json; charset=utf-8');
        header("Content-Length: $size");
        header('Connection: close');
        // flush all output
        ob_end_flush();
        ob_flush();
        flush();
        return $this;
    }

    /**
     * inittializes a longrun php-process
     * close session, maximumexectime etc
     *
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function initLongrun()
    {
        Piwik::setMaxExecutionTime(0);
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        // close Session to unlock filebased sessions
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        return $this;
    }
    
    /*
     * common before/after action methods
     */
    /**
     * execute before a action is called by weblicent
     * - check User access
     * - instantiate the objects (appstore, manager a.so)
     * - save std-GET-parameters to the session
     * - get (generate) the Appstore-UserId of this piwik instance
     *
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function beforeAction()
    {
        Piwik::checkUserIsSuperUser();
        $this->appstore = new PluginMarketplace_Appstore();
        $this->manager = new PluginMarketplace_Manager();
        
        $this->session = new Piwik_Session_Namespace("PluginMarketplace");
        $this->session->setExpirationSeconds(1800);
        
        // store the orgin piwik-params to the session
        $idSite = Piwik_Common::getRequestVar('idSite', -1, 'integer');
        if ($idSite !== -1 || empty($this->session->commonquery)) {
            $idSite = Piwik_Common::getRequestVar('idSite', 1, 'integer');
            $period = Piwik_Common::getRequestVar('period', 'day', 'string');
            $date = Piwik_Common::getRequestVar('date', 'today', 'string');
            $this->session->commonquery = array('idSite' => urlencode($idSite), 
                    'period' => urlencode($period), 
                    'date' => urlencode($date), 
                    'query' => sprintf('idSite=%d&period=%s&date=%s', urlencode($idSite), 
                            urlencode($period), urlencode($date)));
        }
        // fetch UID from appstore
        try {
            $this->uid = $this->appstore->getUid();
        } catch (Exception $e) {
            $this->uid = null;
        }
        // default selected release
        if (!($this->release = $this->session->release)) {
            $this->release = $this->session->release = 'all';
        }
        // Expert Mode (additional "dangerous" features)
        if (!($this->expert = $this->session->expert)) {
            $this->expert = $this->session->expert = false;
        }
        return $this;
    }

    /**
     * called before render a view
     * - export the known attributes
     * - set up admin menue and basicvariables
     *
     * @param Piwik_View $view            
     * @return Piwik_View
     */
    protected function render($view)
    {
        $view->appstoreUID = $this->uid;
        $view->commonquery = $this->session->commonquery;
        $view->expertmode = $this->session->expert;
        $view->appstoreRelease = $this->session->release;
        $view->selfaction = '?module=PluginMarketplace&' . $this->session->commonquery['query'] .
                 "&action=";
        $this->setBasicVariablesView($view);
        print $view->render();
    }

    /**
     * Extend Basicvars with selfaction FQ-link
     * grant superuser access only
     *
     * @see Piwik_Controller_Admin::setBasicVariablesView()
     * @param Piwik_View $view            
     */
    protected function setBasicVariablesView($view)
    {
        $view->cachebuster = uniqid('cb');
        $view->nonce = $this->nonce = uniqid('sfr');
        $view->menu = Piwik_GetAdminMenu();
        return parent::setBasicVariablesView($view);
    }
}