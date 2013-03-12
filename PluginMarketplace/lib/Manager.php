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

/**
 * Include the ochestra
 */
require_once __DIR__ . '/Appstore.php';
require_once __DIR__ . '/Downloader.php';
require_once __DIR__ . '/Installer.php';
require_once __DIR__ . '/Process.php';
// TODO: cache raus
require_once __DIR__ . '/Cache.php';

/**
 * Library: Manager
 *
 *
 * this class handles
 * - conduct the install process
 *    * get download url
 *    * donwload
 *    * extract
 *    * deactivate
 *    * deploy
 *    * activate
 * - get lists of available plugins and their status
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */

class PluginMarketplace_Manager {


    /**
     * current steps of the a task at the install process
     * @var int
     */
    const STEP_FAILED     = -200;
    const STEP_UNKNOWN    = -100;
    const STEP_INIT       = 0;
    const STEP_URL        = 1;
    const STEP_DOWNLOAD   = 2;
    const STEP_EXTRACT    = 3;
    const STEP_VERIFY     = 4;
    const STEP_DEACTIVATE = 5;
    const STEP_DEPLOY     = 6;
    const STEP_ACTIVATE   = 7;
    const STEP_FINISHED   = 8;
    const STEP_REMOVE     = 9;

    CONST CACHE_ID_PLUGINLIST = 'Autopluginlist';

    /**
     * well known attributes of a install task
     * @var string
     */
    const ATTR_SKIPINSTALL    = 'skipinstall';
    const ATTR_SKIPACTIVATE   = 'skipactivate';
    const ATTR_SKIPDEACTIVATE = 'skipdeactivate';
    const ATTR_DEFERDEPLOY    = 'deferredeploy';
    const ATTR_APPSTORE       = 'isAppstoreAvail';
    const ATTR_ISACITVE       = 'isActivated';
    const ATTR_ISALWAYSACT    = 'isAlwaysActivated';
    const ATTR_ISINSTALLED    = 'isInstalled';
    const ATTR_ZIPFILENAME    = 'zip_filename';
    const ATTR_REALNAME       = 'realpluginname';
    const ATTR_URL            = 'download_url';
    const ATTR_EXTRACTPATH    = 'extractbasedir';
    const ATTR_NAME           = 'name';
    const ATTR_APPSTOREINFO   = 'appstoreinfo';

    /**
     * error-codes
     * @var int
     */
    const ERR_UKNOWN   = 1;
    const ERR_API      = 2;
    const ERR_APPSTORE = 3;

    /**
     * cache
     * @var PluginMarketplace_Cache
     */
    protected $cache = null;

    /**
     * List of local available Plugins
     * @var array
     */
    protected $localPlugins;

     
    /**
     * Construtor
     * @param Piwik_CacheFile $cache - optional local cache
     */
    public function __construct(Piwik_CacheFile $cache = null)
    {
        if($cache === null) {
            $cache =  new PluginMarketplace_Cache('PluginMarketplace');
            $cache->setCacheTTL(7200);
        }
        $this->cache = $cache;
    }

    /**
     * Load local plugin configuration
     * @see Piwik_PluginsManager
     * @return array
     */
    public function getLocalPlugins()
    {

        if($this->localPlugins) {
            return $this->localPlugins;
        }

        $plugins = array();

        $pluginManager = Piwik_PluginsManager::getInstance();
        $listPlugins = $pluginManager->readPluginsDirectory();

        foreach($listPlugins as $pluginName)
        {
            $oPlugin = Piwik_PluginsManager::getInstance()->loadPlugin($pluginName);
            $plugins[$pluginName] = array(
                    self::ATTR_ISACITVE         => $pluginManager->isPluginActivated($pluginName),
                    self::ATTR_ISALWAYSACT      => $pluginManager->isPluginAlwaysActivated($pluginName),
                    self::ATTR_ISINSTALLED      => 1,
                    self::ATTR_SKIPINSTALL      => 0,
                    self::ATTR_DEFERDEPLOY      => 0,
                    self::ATTR_NAME             => $pluginName,
                    'isAppstoreAvail'           => 0,
                    'info' =>      array(
                            'description'      => 'unset',
                            'author_homepage'  => '',
                            'author'           => 'unset',
                            'license'          => '',
                            'license_homepage' => '',
                            'version'          => 0,
                            'appstore'         => false,
                    ),
                    self::ATTR_APPSTOREINFO => array(),

            );
        }
        $pluginManager->loadPluginTranslations();

        $loadedPlugins = $pluginManager->getLoadedPlugins();
        foreach($loadedPlugins as $oPlugin)
        {
            $pluginName = $oPlugin->getPluginName();
            $plugins[$pluginName]['info'] = array_merge($plugins[$pluginName]['info'], $oPlugin->getInformation());
        }
        $this->localPlugins = $plugins;
        return $this->localPlugins;
    }


    /**
     * Combine remote available (appstore)plugins with local installed plugins to a single list of plugins
     * @return mixed - array of Plugins
     */
    public function getCurrentPlugins($release = "all")
    {

        $appstore = new PluginMarketplace_Appstore();
        $plugins = $this->getLocalPlugins();
        $remotePlugins = $appstore
        ->setRelease($release)
        ->listPlugins();

        // merge local and remoteplugins and extend the information with the appstore information
        foreach($remotePlugins as $id => $aInfo){
            $plugins[$aInfo['name']] = $this->translatePluginConfig($aInfo, $plugins);
        }

        // mark core-plugins and original Piwik-plugins
        foreach($plugins as $pluginName => $pluginInfo) {
            $plugins[$pluginName]['isCore'] = (isset($pluginInfo['info']['author']) && $pluginInfo['info']['author'] == 'Piwik')? true:false;
            $plugins[$pluginName]['isCore'] |= ($pluginName == 'MultiSites');
        }
        ksort($plugins);
        return $plugins;
    }

    /**
     * Translate the configuration of a Plugin to the internal manager info-structure
     * @param array $appstoreCfg
     * @param array $localPlugins
     * @return array - merged infostructure
     */
    protected function translatePluginConfig($appstoreCfg, $localPlugins = null )
    {

        // default definitition
        $cfg = array(
                self::ATTR_ISACITVE        => 0,
                self::ATTR_ISALWAYSACT     => 0,
                self::ATTR_ISINSTALLED     => 0,
                'info' => array (
                        'description'      => $this->is($appstoreCfg['description'],''),
                        'author_homepage'  => $this->is($appstoreCfg['author_homepage'],''),
                        'author'           => $this->is($appstoreCfg['author'],''),
                        'license'          => $this->is($appstoreCfg['license'],''),
                        'license_homepage' => $this->is($appstoreCfg['license_homepage'],''),
                        'version'          => $this->is($appstoreCfg['version'],''),
                        'appstore' => true
                ),
        );
        // overwite default by a loaded/defined - plugin
        if(is_array($localPlugins) && isset($localPlugins[$appstoreCfg['name']])) {
            $cfg = $localPlugins[$appstoreCfg['name']];
        }
        // extend the info with the appstore info
        $extension = array(
                // major update, so defer the deployment until the last process-step
                self::ATTR_DEFERDEPLOY   => $this->is($appstoreCfg['ismajorupdate'],0),
                // no activation
                self::ATTR_SKIPACTIVATE  => $this->is($appstoreCfg['skipactivation'], 0),
                // no automated install available, download only
                self::ATTR_SKIPINSTALL   => $this->is($appstoreCfg['skipinstall'], 0),
                self::ATTR_APPSTORE      => true,
                self::ATTR_NAME          => $appstoreCfg['name'],
                self::ATTR_APPSTOREINFO      => $appstoreCfg,

        );
        return array_merge($cfg, $extension);
    }


    /**
     * Add a plugin installed or activate through the install-processor
     *
     * @param string|array $pluginWebIds  names of plugins or their Appstore-webId
     * @param boolean add only
     * @throws RuntimeException - if you try to install a plugin, wihich is not available
     * @throws PluginMarketplace_Appstore_APIError_Exception - if the plugin is unknown by the appstore
     * @return PluginMarketplace_Manager
     */
    public function addAppstorePlugins($pluginWebIds, $doActivateOnly=false)
    {
        if(is_string($pluginWebIds)) {
            $pluginWebIds = array($pluginWebIds);
        }
        // defined ('IS_PHPUNIT') && printf("%s start \n", __METHOD__);
        $proc = PluginMarketplace_Process::getInstance();
        $appstore = new PluginMarketplace_Appstore();
        $currentPlugins=$this->getCurrentPlugins();

        foreach($pluginWebIds as $pluginWebId) {
            if(isset($currentPlugins[$pluginWebId])) {
                // Fallback: the given $pluginWebId is in fact a pluginName
                $plugInfo = $currentPlugins[$pluginWebId];
            } else {
                // try to fetch the Info from the appstore
                $plugInfo = $this->translatePluginConfig($appstore->getPluginInfo($pluginWebId), $currentPlugins);
            }

            // defined ('IS_PHPUNIT') && printf("%s  add %s\n", __METHOD__, print_r($plugInfo, true));

            if($this->is($plugInfo[self::ATTR_SKIPINSTALL],false)){
                throw new RuntimeException('cannot autoinstall Pluginname' . $pluginWebId,101);
            }
            $proc->addTask($pluginWebId,  $plugInfo);


            if($doActivateOnly ||  !$plugInfo[self::ATTR_APPSTORE]){
                $proc->setTaskAttribute($pluginWebId, self::ATTR_SKIPACTIVATE,false)
                ->setTaskStep($pluginWebId, self::STEP_ACTIVATE);
            }
        }
        return $this;
    }

    /**
     * Remove Plugins
     *
     * @param string|array $pluginWebIds  names of plugins
     * @throws RuntimeException - if you try to install a plugin, wihich is not available
     * @throws PluginMarketplace_Appstore_APIError_Exception - if the plugin is unknown by the appstore
     * @return PluginMarketplace_Manager
     */
    public function addRemovePlugins($pluginNames)
    {
        if(is_string($pluginNames)) {
            $pluginNames = array($pluginNames);
        }
        $proc = PluginMarketplace_Process::getInstance();
        $currentPlugins=$this->getCurrentPlugins();
        foreach($pluginNames as $pluginName) {
            if(!isset($currentPlugins[$pluginName])) {
                throw new RuntimeException('Plugin is not installed'. $pluginName);
            }
             
            $plugInfo = $currentPlugins[$pluginName];
            // defined ('IS_PHPUNIT') && printf("%s  remove %s\n", __METHOD__, print_r($plugInfo, true));

            // add task to be removed in deferred mode deferred
            $proc->addTask($pluginName,  $plugInfo)
            ->setTaskStep($pluginName, self::STEP_REMOVE)
            ->markTaskProcessed($pluginName, self::STEP_REMOVE, PluginMarketplace_Process::STATUS_DEFERRED);
        }
        return $this;
    }


    /**
     * Ad a Plugin to be activated throug the stepping-process
     * @param string|array $pluginNames
     * @throws RuntimeException - if Pluginname is unknown
     * @return PluginMarketplace_Manager
     */
    public function addActivatePlugins($pluginNames)
    {
        return $this->addAppstorePlugins($pluginNames, true);
    }

    /**
     * Add a (zip)file to the installer process
     * extract/deffered Deployment, no activation
     * @param string $filename
     * @return PluginMarketplace_Manager
     */
    public function addUploadPlugin($filename)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $downloader = new PluginMarketplace_Downloader();
        // register the file to the downloder
        $downloader->upload($filename);

        // add a task to extract and deploy the zip-file
        // skip activation/deactivation and defer deployment,
        // cause we don't know anything about the content and the reuquirements of the zip-filed plugin
        $proc->addTask('Fileupload',array())
        ->setTaskAttribute('Fileupload', self::ATTR_ZIPFILENAME,    $downloader->getFilename())
        ->setTaskAttribute('Fileupload', self::ATTR_SKIPACTIVATE,   true)
        ->setTaskAttribute('Fileupload', self::ATTR_SKIPDEACTIVATE, true)
        ->setTaskAttribute('Fileupload', self::ATTR_DEFERDEPLOY,    true)
        ->setTaskAttribute('Fileupload', self::ATTR_NAME,           'FileUpload')
        ->setTaskStep('Fileupload', self::STEP_EXTRACT);
        return $this;
    }


    /**
     * Check the Process status (runable or running)
     * @param boolean $isRunable - true? check if the Process is runable, false check, if process is running
     * @return boolean
     */
    public function checkProcess($isRunable=false)
    {
        $proc = PluginMarketplace_Process::getInstance();
        return $isRunable? $proc->isRunable(): $proc->isRunning();
    }

    /**
     * Reset the process "Hard"
     * @return PluginMarketplace_Manager
     */
    public function resetProcess()
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->reset(true);
        return $this;
    }

    /**
     * Run a process
     * @throws RuntimeException
     * @return PluginMarketplace_Manager
     */
    public function run()
    {
        $proc = PluginMarketplace_Process::getInstance();
        //  defined ('IS_PHPUNIT') && printf("%s  RUN %s\n", __METHOD__, print_r($proc->getStatus(), true));
        if($proc->isRunning()){
            throw new RuntimeException('Another Process is still running');
        }
        if(! $proc->hasTasks()){
            // nothing to do
            throw new RuntimeException('No Task to install');
        }
        $proc->start();
        $this->loop();
        $proc->stop();
        return $this;
    }

    /**
     * Loop the steps until there is nothing more left to do
         *
     * @throws RuntimeException
     * @return PluginMarketplace_Manager
     */
    protected function loop()
    {
        $proc = PluginMarketplace_Process::getInstance();
        $maxStepCounter = 100;
        foreach($proc as $taskId => $pluginInfo) {
            //   defined ('IS_PHPUNIT') && printf("%s  Process %s %s\n", __METHOD__, $taskId, print_r($pluginInfo, true));
            !defined('IS_PHPUNIT') && sleep(1); // FIXME: sleep for testing purpose online-ajax query


            $proc->addTaskHistory($taskId, 'foreach');
            try {
                switch($pluginInfo['step']){
                    case PluginMarketplace_Process::STEP_UNKNOWN: // start downloader
                    case self::STEP_INIT:
                    case self::STEP_URL: // get URL
                        $this->processAppstoreUrl($taskId);
                        break;
                    case self::STEP_DOWNLOAD:
                        $this->processDownload($taskId);
                        break;
                    case self::STEP_EXTRACT:
                        $this->processExtract($taskId);
                        break;
                    case self::STEP_VERIFY:
                        $this->processVerify($taskId);
                        break;
                    case self::STEP_DEPLOY:
                        $this->processDeploy($taskId);
                        break;
                    case self::STEP_ACTIVATE:
                        $this->processActivate($taskId);
                        break;
                    case self::STEP_DEACTIVATE:
                        $this->processDeactivate($taskId);
                        break;
                    case self::STEP_REMOVE:
                        $this->processRemove($taskId);
                        break;
                    case self::STEP_FINISHED:
                        $proc->markTaskProcessed($taskId);
                        break;
                    default:
                        // throw new RuntimeException('WARNING!!! unkown processtep:' .print_r($proc->getStatus(), true));
                        $proc->addTaskHistory($taskId, 'WARNING!!! unkown processtep');
                        $proc->markTaskProcessed($taskId);
                }
            } catch (PluginMarketplace_Appstore_APIError_Exception $e) {
                $msg = sprintf('Step "%d" failed!!! Error: %d , Exception %s',$pluginInfo['step'], $e->getCode(),  $e->getMessage() );
                $proc->markTaskFailed($taskId, $msg, self::ERR_APPSTORE, self::STEP_FAILED);
            } catch (PluginMarketplace_Appstore_Connection_Exception $e) {
                $msg = sprintf('Step "%d" failed!!! Error: %d , Exception %s',$pluginInfo['step'], $e->getCode(),  $e->getMessage() );
                $proc->markTaskFailed($taskId, $msg, self::ERR_APPSTORE, self::STEP_FAILED);
            } catch (Exception $e) {
                $msg = sprintf('Step "%d" failed!!! Error: %d , Exception %s',$pluginInfo['step'], $e->getCode(),  $e->getMessage() );
                $proc->markTaskFailed($taskId, $msg, self::ERR_UKNOWN, self::STEP_FAILED);
            }

            if( $maxStepCounter-- < 0 ) {
                throw new RuntimeException('To many steps in process-loop');
            }
        }
        return $this;
    }


    /*
     * Proces steps to install a plugin
    */

    /**
     * Process STEP 1
     * get the url for download form the appstore
     * @param sting $pluginName - webId or name of a plugin, wihich is retreivable from appstore
     * @return PluginMarketplace_Manager
     */
    protected function processAppstoreUrl($pluginName)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_URL);
        $appstore = new PluginMarketplace_Appstore();
        $ainfo = $proc->getTaskAttribute($pluginName,self::ATTR_URL);
        $proc->addTaskHistory($pluginName, 'chek info '.print_r($ainfo,true));
        if(!$ainfo || empty($ainfo['download_url']) )  {
            // fallback an try to get the URL
            $proc->addTaskHistory($pluginName, 'no donwload_url, try to fetch via Appstore');
            $url = $appstore->getDownloadUrl($pluginName);
        } else {
            $url = $ainfo['download_url'];
        }
        //defined ('IS_PHPUNIT') && printf("%s  Url  %s\n", __METHOD__, $url);
        $proc->setTaskAttribute($pluginName, self::ATTR_URL, $url)
        ->markTaskProcessed($pluginName, self::STEP_DOWNLOAD);
        return $this;
    }

    /**
     * Process STEP II
     * download a Plugin with the download URL
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processDownload($pluginName)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_DOWNLOAD);
        $downloader = new PluginMarketplace_Downloader();

        $url = $proc->getTaskAttribute($pluginName,self::ATTR_URL);
        $filename = uniqid('download_');
        // defined ('IS_PHPUNIT') && printf("%s URL:%s  in \n%s\n",__METHOD__,$url, print_r($proc->getTaskAttribute($pluginName), true));
        $filename = $downloader->download($url, $filename);
        // defined ('IS_PHPUNIT') && printf("%s  Filename  %s\n", __METHOD__,$filename);
        $proc->setTaskAttribute($pluginName, self::ATTR_ZIPFILENAME, $filename)
        ->markTaskProcessed($pluginName, self::STEP_EXTRACT);
        return $this;
    }

    /**
     * Process STEP III
     * Unzip the downloaded Zip File
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processExtract($pluginName) {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_EXTRACT);
        $downloader = new PluginMarketplace_Downloader();

        $subdir = uniqid('extract_');
        $filename = $proc->getTaskAttribute($pluginName,self::ATTR_ZIPFILENAME);

        $extractDir = $downloader->setFilename($filename)->extract($subdir);
        $proc->setTaskAttribute($pluginName, self::ATTR_EXTRACTPATH, $extractDir)
        ->markTaskProcessed($pluginName, self::STEP_VERIFY);
        return $this;
    }

    /**
     * Process STEP IV
     * verify the plugin with basic-tests
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processVerify($pluginName)
    {

        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_VERIFY);
        $installer = new PluginMarketplace_Installer();

        $baseDir = $proc->getTaskAttribute($pluginName,self::ATTR_EXTRACTPATH);

        $realPluginName = $installer->setWorkspace($baseDir)->getPluginName();
        $installer->verify();
        $proc
        ->setTaskAttribute($pluginName, self::ATTR_REALNAME, $realPluginName)
        ->setTaskAttribute($pluginName, self::ATTR_NAME,    $realPluginName)
        ->markTaskProcessed($pluginName, self::STEP_DEACTIVATE);
        return $this;
    }


    /**
     * Process STEP V
     * Deactivate the Plugin
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processDeactivate($pluginName) {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_DEACTIVATE);

        // skip deactivate, if the plugin was not active, requested or the plugin is the PluginMarketplace
        if($proc->getTaskAttribute($pluginName,self::ATTR_SKIPDEACTIVATE)
                || !$proc->getTaskAttribute($pluginName,self::ATTR_ISACITVE)
                || $proc->getTaskAttribute ($pluginName,self::ATTR_NAME) == 'PluginMarketplace' ) {
            $proc->addTaskHistory($pluginName,'skip deaktivation')
            ->markTaskProcessed($pluginName, self::STEP_DEPLOY);
            return $this;
        }

        $installer = new PluginMarketplace_Installer();
        $realPluginName =  $proc->getTaskAttribute($pluginName, self::ATTR_REALNAME);
        $installer->setPluginName($realPluginName)->deactivate();
        $proc->markTaskProcessed($pluginName,self::STEP_DEPLOY);
        return $this;
    }


    /**
     * Process STEP V
     * deploy the verified Plugin to PIWIK_ROOT
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processDeploy($pluginName)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_DEPLOY);

        // if has major update aka deferred deployment was requestested,
        // than mark the task to be processed at the "postinstall" step
        if($proc->getTaskAttribute($pluginName,self::ATTR_DEFERDEPLOY)) {
            $proc->addTaskHistory($pluginName,'deferred deployment')
            ->setTaskAttribute($pluginName, self::ATTR_DEFERDEPLOY, false)
            ->markTaskProcessed($pluginName, self::STEP_DEPLOY, PluginMarketplace_Process::STATUS_DEFERRED);
            return $this;
        }


        $installer = new PluginMarketplace_Installer();
        $baseDir = $proc->getTaskAttribute($pluginName,self::ATTR_EXTRACTPATH);

        // do it
        $installer->setWorkspace($baseDir)->deploy();

        // unlink sources
        $downloader = new PluginMarketplace_Downloader();
        $filename = $proc->getTaskAttribute($pluginName,self::ATTR_ZIPFILENAME);
        try {
            $downloader
            ->setFilename($filename)
            ->setextractedPath($baseDir)
            ->unlink();
        } catch (Exception $e){
            // Silently ignore, if unlink of tmp files failed
            $proc->addTaskHistory($pluginName, "Unlink sources failed:". $e->getMessage());
        }
        $proc->markTaskProcessed($pluginName, self::STEP_ACTIVATE);
        return $this;
    }


    /**
     * Force Remove/Deletion of a plugin
     * @param string $pluginName
     * @return PluginMarketplace_Manager
     */
    protected function processRemove($pluginName)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_REMOVE);


        $installer = new PluginMarketplace_Installer();
        $installer->setPluginName($pluginName);

        try {
            $installer->remove();
        } catch (Exception $e){
            // somting went wrong...
            $proc->addTaskHistory($pluginName, "Remove Failed:". $e->getMessage());

        }
        if($installer->getStatus() == PluginMarketplace_Installer::STATUS_REMOVED) {
            // there might be some errors while removing, but at the the end, the plugin was deleted
            $proc->markTaskProcessed($pluginName, self::STEP_FINISHED);
        } else {
            $proc->markTaskFailed($pluginName, 'failed with a serious error',self::ERR_UKNOWN, self::STEP_FINISHED );
        }
        return $this;
    }



    /**
     * Process STEP VI
     * activate the plugin
     * @param unknown $pluginName
     * @throws RuntimeException - if the plugin was not installable
     * @return PluginMarketplace_Manager
     */
    protected function processActivate($pluginName)
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->markTaskprocessing($pluginName, self::STEP_ACTIVATE);

        if($proc->getTaskAttribute($pluginName,self::ATTR_SKIPACTIVATE)) {
            $proc->addTaskHistory($pluginName,'skip activation')
            ->markTaskProcessed($pluginName, self::STEP_FINISHED);
            return $this;
        }
        
        $realPluginName =  $proc->getTaskAttribute($pluginName, self::ATTR_REALNAME);
        if(!$realPluginName) {
            // fallback
            $realPluginName = $pluginName;
        }

        // try to activate the plugin
        $installer = new PluginMarketplace_Installer();
        try {
            $installer
            ->setPluginName($realPluginName)
            ->activate();
        } catch (Exception $e ){
            // ignore the "already activated" Exception, which are possibly through cache-Problems in PluginManager.
            if( !strstr($e->getMessage(), 'already')) {
                throw new RuntimeException('canot activate plugin:' .$e->getMessage(),100, $e);
            }
        }
        $proc->markTaskProcessed($pluginName, self::STEP_FINISHED);
        return $this;
    }


    /**
     * Things to be done after all tasks has been processed
     * e.g. activation of plugins with majorupdates
     */
    public function postProcess()
    {
        $proc = PluginMarketplace_Process::getInstance();
        $proc->addTaskHistory(null, 'execute Postprocess');
        // enable all tasks that were marked as deferred
        $proc->reinitDefferedTasks();
        // rerun the task-loop to process those tasks
        $this->run();
        return $this;
    }


    /**
     * Load current status of the installprocess
     * @param string|null $pluginName
     * @return mixed
     */
    public function getStatus($pluginName = null, $itemInfo = null)
    {
        $proc = PluginMarketplace_Process::getInstance();
        return $proc->getStatus($pluginName, $itemInfo);
    }


    /**
     * convenience method to check if given value is set. if so, value is return, otherwise the default
     * @param mixed $arg value to check
     * @param mixed $default value returned if $value is unset
     */
    protected function is(& $arg, $default = null)
    {
        if (isset($arg)) {
            return $arg;
        }
        return $default;
    }
}