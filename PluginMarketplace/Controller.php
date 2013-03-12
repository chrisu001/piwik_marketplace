<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://plugin.suenkel.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @author Christian Suenkel <christian@suenkel.de>
 *
 * @category Piwik_Plugins
 * @package  Piwik_PluginMarketplace
 */


require_once __DIR__ . '/lib/Appstore.php';
require_once __DIR__ . '/lib/Installer.php';
require_once __DIR__ . '/lib/Manager.php';
require_once __DIR__ . '/lib/Prerequisite.php';

/**
 * Webfrontend-Controller
 *
 * @package Piwik_PluginMarketplace
 * @subpackage controller
 */
class Piwik_PluginMarketplace_Controller extends Piwik_Controller_Admin
{

    /**
     * Appstore-Connector
     * @var PluginMarketplace_Appstore
     */
    protected $appstore;

    /**
     * process-manager to step through the installprocess
     * @var PluginMarketplace_Manager
     */
    protected $manager = null;

    /**
     * Session
     * @var Piwik_Session_Namespace
     */
    protected $session = null;

    /**
     * Unique ID wich wil be generated for each instance of a  piwik-installation
     * @var string
     */
    protected $uid = null;

    /**
     * Selected release (developer, unittest, alpha, beta, stable, all)
     * @var string
     */
    protected $release = null;

    /**
     * Falg to enable additional expert-features such as "remove"plugin
     * @var boolean
     */
    protected $expert = false;

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
        } catch (Exception $e ) {
            // TODO: handle the Exception gracefully and display proper error-descriptions
            throw $e;
        }

        try{
            $this->manager->resetProcess();
        } catch (Exception $e) {
            // if the process is not resetable -> process still running -> display the current status
            return Piwik::redirectToModule('PluginMarketplace','install',array('statusonly' => 1));
        }

        /*
         * If there were some plugins to be installed/activated, then add them to the Installprocess and
        * run the installation
        */
        $selectPlugins = Piwik_Common::getRequestVar('pluginstall', array(), 'array');
        if(!empty($selectPlugins)) {
            $this->manager->addAppstorePlugins($selectPlugins);
        }
        $activatePlugins = Piwik_Common::getRequestVar('plugactivate', array(), 'array');
        if(!empty($activatePlugins)) {
            $this->manager->addActivatePlugins($activatePlugins);
        }
        if(!empty($selectPlugins) || !empty($activatePlugins)) {
            $newUrl = sprintf('index.php?module=PluginMarketplace&action=install&idSite=%d&period=%s',
                    Piwik_Common::getRequestVar('idSite', 1, 'integer'),
                    Piwik_Common::getRequestVar('period', 'yesterday', 'string'));
            // TODO: use $this->session->commonquery and redirect to Module
            Piwik_Url::redirectToUrl($newUrl);
            exit();
        }

        /*
         *  display "normal" index-page
        */
        $view = Piwik_View::factory('index');
        if(!Piwik_Config::getInstance()->isFileWritable())
        {
            $view->configFileNotWritable = true;
        }
        print $this->beforeRender($view)->render();
    }
    
    /**
     * Rss-Feed widget
     */
    public function rss()
    {
        $appstore = new PluginMarketplace_Appstore();
        
        $view = Piwik_View::factory('rsswidget');
        $view->error =  false;
        try {
            $rss = $appstore->getRss();
        } catch (Exception $e) {
            $view->error = $e->getMessage();
        }
        $view->rss = $rss;
        print $view->render();
    }
    
    /**
     * Remove a plugin
     */
    public function remove()
    {
        $this->beforeAction();
        try{
            $this->manager->resetProcess();
        } catch (Exception $e) {
            // if the process is not resetable -> process still running -> display the current status
            return Piwik::redirectToModule('PluginMarketplace','install',array('statusonly' => 1));
        }
        $selectPlugins = Piwik_Common::getRequestVar('pluginNames', array(), 'array');
        $this->manager->addRemovePlugins($selectPlugins);
        $newUrl = sprintf('index.php?module=PluginMarketplace&action=install&idSite=%d&period=%s',
                Piwik_Common::getRequestVar('idSite', 1, 'integer'),
                Piwik_Common::getRequestVar('period', 'yesterday', 'string'));
        // TODO: use $this->session->commonquery and redirect to Module
        Piwik_Url::redirectToUrl($newUrl);
        exit();
    }
    


    /**
     * Display the (ajaxloaded) table of installed Plugins
     */
    public function indexlist()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('index_list');
        $plugins = $this->manager->getCurrentPlugins($this->release);
        $view->pluginsName = $plugins;
        $view->debug = print_r($plugins,true);
        print $this->beforeRender($view)->render();
    }
    
    
    /**
     * Display the (ajaxloaded) table of installed Plugins
     */
    public function indexexpert()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('index_expert');
        $plugins = $this->manager->getCurrentPlugins($this->release);
        $view->pluginsName = $plugins;
        $view->debug = print_r($plugins,true);
        print $this->beforeRender($view)->render();
    }
    

    /**
     * Ajax called by the "advanced"-tab
     * select the release to be used while displaying
     * (stable, alpha, beta, developer, unittest, all)
     */
    public function switchrelease()
    {
        $this->beforeAction();
        $release = Piwik_Common::getRequestVar('release', 'stable', 'string');
        if(! in_array($release, array('all', 'unittest', 'developer', 'alpha', 'beta', 'stable'))){
            $release = 'all'; // default
        }
        $this->session->release = $release;
        $this->replyJson(true, array('release' => $this->session->release));
    }


    /**
     * Ajax called by the "advanced"-tab
     * switch expert mode true/false
     */
    public function switchexpert()
    {
        $this->beforeAction();
        $expert = Piwik_Common::getRequestVar('expert', 0, 'integer');
        $this->session->expert = ($expert == 1);
        $this->replyJson(true, array('expert' => $this->session->expert));
    }


    /**
     * Display the (ajaxloaded) Feedback
     */
    public function indexfeedback()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('index_feedback');
        // Try to catch emailadress for feedback prefilled
        $view->userEmail = '';
        try {
            if(Piwik::isUserIsSuperUser()){
                $view->userEmail = urlencode(Piwik::getSuperUserEmail());
            } else {
                $userLogin = Piwik::getCurrentUserLogin();
                $user = Piwik_UsersManager_API::getInstance()->getUser($userLogin);
                $view->userEmail = urlencode($user['email']);
            }
        } catch (Exception $e) {
        }
        print $this->beforeRender($view)->render();
    }


    /**
     * Upload a zipFiled Plugin to be deployed
     */
    public function upload()
    {
        $this->beforeAction();
        // if there is still a backendprocess is running-> display its status
        try{
            $this->manager->resetProcess();
        } catch (Exception $e) {
            return Piwik::redirectToModule('PluginMarketplace','install',array('statusonly' => 1));
        }

        $view = Piwik_View::factory('upload');
        if(empty($_FILES)) {
            // display upload form
            print $this->beforeRender($view)->render();;
            return;
        }
        /*
         * small precheck of uploaded file
        */
        if($_FILES['userfile']['type'] != "application/x-zip-compressed") {
            $view->error=Piwik_Translate('APUA_Upload_error_ziponly');
            print $this->beforeRender($view)->render();
            return;
        }
        if($_FILES['userfile']['size'] < 10 && $_FILES['userfile']['error'] == 4) {
            $view->error=Piwik_Translate('APUA_Upload_error_nofile');
            print $this->beforeRender($view)->render();
            return;
        }
        if($_FILES['userfile']['error'] !== 0) {
            $view->error=Piwik_Translate('APUA_Upload_error_unknown');
            print $this->beforeRender($view)->render();
            return;
        }
        // TODO: additional preflight checks (content-analyse etc)

        /*
         * and lets run the install process
        */
        $this->manager->addUploadPlugin($_FILES['userfile']['tmp_name']);
        Piwik::redirectToModule('PluginMarketplace','install');
    }


    /**
     * Display install-Status of a running backend process
     * this page
     * - (re)starts the process, if needed, via an ajax call
     * - polls via ajax the current status and display its progress
     */
    public function install()
    {
        $this->beforeAction();
        $view = Piwik_View::factory('install');

        $view->plugstatus = json_encode($this->mapStatus());
        $doRun = Piwik_Common::getRequestVar('statusonly', 0, 'int') == 1? false:true;
        // flag, if the page should try to start the process via the ajaxcall  if runable;
        $doRun |= $this->manager->checkProcess(true);
        $view->doRun = $doRun;
        // $view->debug = print_r($this->manager->getStatus(), true);
        print $this->beforeRender($view)->render();
    }
    
   
    /**
     * what to to, if the installprocess was finished -> redirect to the index
     */
    public function installfinish()
    {
        $this->beforeAction();
        Piwik::redirectToModule('PluginMarketplace','index');
    }


    /**
     * Last Step after install
     * ajaxcalled by install-page
     * run deferred install steps (which could compromise the piwik-instance, like core-updates a.s.o)
     */
    public function postinstall()
    {
        $this->beforeAction()
        ->initLongrun();
        $this->manager->postProcess();
        $this->replyJson(true, $this->mapStatus());
    }


    /**
     * ajax-poll. retreive the current status of the backenprocess
     * used by "install"-page
     */
    public function status()
    {
        $this->beforeAction();
        $this->replyJson(true, $this->mapStatus());
    }


    /**
     * ajax called by the install-page
     * try ro run a installpocess (ajax)
     * closes the http-connection and tries to continue the install process in the "background"
     */
    public function run()
    {
        $this->beforeAction();

        if(1 == Piwik_Common::getRequestVar('debug', 0, 'integer')){
            $this->manager->run();
            $this->replyJson(true, $this->manager->getStatus());;
            return;
        }
        $this->initLongrun()->replyJson(true);
        $this->manager->run();
        exit();
    }


    /**
     * Translate the current internal status of the backendprocess to the webfront-api-format
     * @return array
     */
    protected function mapStatus()
    {
        $mapStatus= $this->manager->getStatus();

        // core status of the process
        $status = array(
                'isRunning'   => $mapStatus['isRunning'],
                'sequence'    => $mapStatus['process']['sequence'],
                'isRunnable'  => $mapStatus['isRunable'],
                'statuscode'  => $mapStatus['process']['status'],
                'items' => array());

        // status of the tasks (each plugin has its own install-task)
        foreach($mapStatus['tasks'] as $pluginName => $task) {
            $info=$task['info'];
            //@see Manager::ERR_*
            $exception = isset($task['errorcode']) ? Piwik_Translate('APUA_Process_Error_' .$task['errorcode']):'';
            $name = $info[PluginMarketplace_Manager::ATTR_NAME];
            $item = array(
                    'name'        => $name,
                    'step'        => Piwik_Translate('APUA_STEP_'. $task['step']),
                    'status'      => Piwik_Translate('APUA_STATUS_'. $task['status']),
                    'skipinstall' => empty($info[PluginMarketplace_Manager::ATTR_SKIPINSTALL]) ? false : true,
                    'statuscode'  => $task['status'],
                    'reason'      => $exception,
                    'errorcode'   => empty($task['errorcode']) ? 0 : $task['errorcode'],
            );
            $status['items'][$name] = $item;
        }

        // while Unittesting, add the full backendstatus
        if( 'live' == 'jenkins') {
            $status['org'] = $mapStatus;
        }
        return $status;
    }


    /**
     * helper function to reply a message to the frontend as json-encoded ajax response
     * @param boolean $success - if the RMC was successfull
     * @param mixed $payload - return value of RMC
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function replyJson($success, $payload = null, $failReason = '')
    {
        $msg = array('success' => $success, 'payload'=> $payload);
        $success === false && $msg['reason'] =  $failReason;
        $msg = json_encode($msg);

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
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function initLongrun()
    {
        Piwik::setMaxExecutionTime(0);
        if(function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        // close Session to unlock filebased sessions
        if(function_exists('session_write_close')){
            session_write_close();
        }
        return $this;
    }


    /*
     * common before/after action methods
    */
    /**
     * execute before  a action is called by weblicent
     * - check User access
     * - instantiate the objects (appstore, manager a.so)
     * - save std-GET-parameters to the session
     * - get (generate) the Appstore-UserId of this piwik instance
     * @return Piwik_PluginMarketplace_Controller
     */
    protected function beforeAction()
    {
        Piwik::checkUserIsSuperUser();
        $this->appstore = new PluginMarketplace_Appstore();
        $this->manager = new PluginMarketplace_Manager();
        $this->session = new Piwik_Session_Namespace("Piwik_PluginMarketplace");
        $this->session->setExpirationSeconds(1800);


        $idSite = Piwik_Common::getRequestVar('idSite', 0, 'integer');
        if($idSite !== 0  || empty($this->session->commonquery)) {
            $idSite = Piwik_Common::getRequestVar('idSite', 1, 'integer');
            $period = Piwik_Common::getRequestVar('period', 'day', 'string');
            $date = Piwik_Common::getRequestVar('date', 'today', 'string');
            $this->session->commonquery =array(
                    'idSite' => urlencode($idSite),
                    'period' => urlencode($period),
                    'date'   => urlencode($date),
                    'query'  => sprintf('idSite=%d&period=%s&date=%s', urlencode($idSite), urlencode($period), urlencode($date))
            );
        }
        try {
            $this->uid = $this->appstore->getUid();
        } catch (Exception $e) {
            $this->uid = null;
        }
        if(! ($this->release = $this->session->release)){
            $this->release = 'all';
        }
        // Expert Mode (additional "dangerous" features)
        if(! ($this->expert = $this->session->expert )){
            $this->expert = false;
        }

        return $this;
    }


    /**
     * called before render a view
     * - export the known attributes
     * - set up admin menue and basicvariables
     * @param Piwik_View $view
     * @return Piwik_View
     */
    protected function beforeRender(&$view)
    {
        $view->appstoreUID = $this->uid;
        $view->commonquery = $this->session->commonquery;
        $view->expertmode = $this->session->expert;
        $view->appstoreRelease = $this->release;
        $this->setBasicVariablesView($view);
        $view->menu = Piwik_GetAdminMenu();
        return $view;
    }
}