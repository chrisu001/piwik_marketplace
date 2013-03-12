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
 * Library: Installer
 *
 *
 * this class handles
 * - deployment (copy) a plugin from its tmp(download) path to the piwik instance
 * - basic verify the plugin
 * - acitvate/deactivate the plugin
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_InstallerCore
{

    /**
     * Absolute Path of the tmp-directory where the plugin could be found
     * $workspace/plugins/$pluginName
     * @var string
     */
    protected $workspace = null;

    /**
     * Status constants as result of the install process
     * @var int
     */
    CONST STATUS_REMOVED     = -2;
    CONST STATUS_FAILED      = -1;
    CONST STATUS_INIT        =  0;
    CONST STATUS_ACTIVATED   =  4;
    CONST STATUS_DEACTIVATED =  5;

    /**
     * current status of the install process
     * @var int - self::STATUS_* constants
     */
    protected $status = 0;

    /**
     * Name of the current plugin to be processed
     * @var string
     */
    protected $pluginName = null;

    /**
     * while enabling/disabling the maintenace-mode store the currennt values
     * @var int
     */
    private $trackerModeOld = null;
    private $generalModeOld = null;

    /**
     * Constructor
     * gets the current absolute (extracted)path of the plugin to be installed
     * @param string $workspace
     */
    public function __construct($workspace = null )
    {
        $this->setWorkspace($workspace);
    }

    /*
     * get/set
    */
    /**
     * Retreieve the current status of the install process
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Set the absolute-path
     * this also resets the internal status to INIT
     * @param string $workspace
     * @return PluginMarketplace_Installer
     */
    public function setWorkspace($workspace = null, $createAsUserPath = false)
    {
        $this->status = self::STATUS_INIT;
        if ($createAsUserPath == true && $workspace !== null){
            $workspace = PIWIK_USER_PATH . DIRECTORY_SEPARATOR
            . 'tmp' . DIRECTORY_SEPARATOR
            . 'installer' . DIRECTORY_SEPARATOR
            . $workspace;
            !is_dir($workspace) && Piwik_Common::mkdir($workspace);
            $workspace = realpath($workspace);
        }
        $this->workspace = $workspace;
        return $this;
    }

    /**
     * get the current workspace-directory path
     * @throws PluginMarketplace_Installer_Exception - if the workspace is not existant/accessible
     * @return string
     */
    public function getWorkspace()
    {
        if( $this->workspace == null ){
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_nobase'));
        }
        if( !is_dir($this->workspace) ){
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_nobasedir', $this->workspace));
        }
        return realpath($this->workspace). DIRECTORY_SEPARATOR;
    }


    /**
     * set the current Pluginname
     * @param string $pluginName
     * @return PluginMarketplace_Installer
     */
    public function setPluginName($pluginName)
    {
        $this->pluginName = $pluginName;
        return $this;
    }


    /**
     * get the current Pluginname
     * @return string
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }


    /**
     * tries to fetch and verify the PHP-classfilename of the plugin
     * @throws PluginMarketplace_Installer_Exception - if the expected classfile does not exist
     * @return string - absolute path to the classfile
     */
    protected function getPluginPHPClassFilename()
    {
        $phpclassfilename = $this->getWorkspace(). DIRECTORY_SEPARATOR
        .'plugins' . DIRECTORY_SEPARATOR
        .$this->getPluginName() . DIRECTORY_SEPARATOR
        .$this->getPluginName() . '.php';
        if(!is_file($phpclassfilename)) {
            $this->status = self::STATUS_FAILED;
            $content = glob(dirname($phpclassfilename). DIRECTORY_SEPARATOR .'*');
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_noplugfile',htmlentities(print_r($content,true))));
        }
        return $phpclassfilename;
    }
     
     
    /**
     * Activate a plugin
     * tries to activate a plugin in Piwik
     * @throws PluginMarketplace_Installer_Exception - if a previous step failed
     * @return PluginMarketplace_Installer
     */
    public function activate ()
    {
        if($this->status <= self::STATUS_FAILED) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal', ''));
        }
        $pluginName = $this->getPluginName();
        Piwik_Config::getInstance()->clear();

        // Check existance
        $availablePlugins =  Piwik_PluginsManager::getInstance()->readPluginsDirectory();
        if(!in_array($pluginName, $availablePlugins)){
            $this->status = self::STATUS_FAILED;
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal','not exist'));
        }

        try {
            Piwik_PluginsManager::getInstance()->activatePlugin($pluginName);
            Piwik_Config::getInstance()->forceSave();
        } catch (Exception $e) {
            // fetch the "already activated" exception silently
            if(!strstr($e->getMessage(),'already')) {
                $this->status = self::STATUS_FAILED;
                throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal',''),NULL,$e);
            }
        }
        $this->status = self::STATUS_ACTIVATED;
        return $this;
    }


    /**
     * deactivate a plugin
     * tries to deactivate a plugin
     * @throws PluginMarketplace_Installer_Exception - if a previous step failed
     * @return PluginMarketplace_Installer
     */
    public function deactivate () {
        if($this->status <= self::STATUS_FAILED) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal', ''));
        }
        $pluginName = $this->getPluginName();
        Piwik_Config::getInstance()->clear();
        try {
            Piwik_PluginsManager::getInstance()->deactivatePlugin($pluginName());
        } catch (Exception $e) {
            // fetch the "already deactivated" exception silently
            if(!strstr($e->getMessage(),'already')) {
                $this->status = self::STATUS_FAILED;
                throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal',''),NULL,$e);
            }
        }
        $this->status = self::STATUS_DEACTIVATED;
        return $this;
    }


    /**
     * tries to remove a Plugin (incl delete its directory)
     * handle with caution!!!
     * //TODO: this method should be integrated to the Piwik_Plugin_Manager
     * @throws PluginMarketplace_Installer_Exception - if someting fails
     * @return PluginMarketplace_Installer
     */
    public function remove()
    {
        $this->setWorkspace('pluginbackup', true);
        $backupDir = $this->getWorkspace();

        $config = Piwik_Config::getInstance();
        $config->init();

        $pm = Piwik_PluginsManager::getInstance();

        $e = null;
        $error_serious = null;
        $emessage=array();
        // be nice to Piwik: deactivate
        $pluginName = $this->getPluginName();
        if($pm->isPluginActivated($pluginName)) {
            try {
                $pm->deactivatePlugin($pluginName);
            } catch( Exception $tmpE ) {
                $e = $tmpE;
                $emessage['deactivate'] = $tmpE->getMessage();
            }
        }


        $oPlugin = null;
        try {
            $oPlugin = $pm->loadPlugin( $pluginName );
        } catch( Exception $tmpE ) {
            $e = $tmpE;
            $emessage['load'] = $tmpE->getMessage();
        }
         
        // STEP I maintenance mode
        $this->enableMaintenance();

        //STEP II: deregister Plugin-hooks
        try {
            $oPlugin !== null && $pm->unloadPlugin($oPlugin);
        } catch( Exception $tmpE ) {
            $e = $tmpE;
            $emessage['unload'] = $tmpE->getMessage();
        }
        //STEP:  Call the Uninstall routine of the Plugin
        if($oPlugin !== null && method_exists($oPlugin, 'uninstall')) {
            try{
                call_user_func(array($oPlugin,'uninstall'));
            } catch( Exception $tmpE ) {
                $e = $tmpE;
                $emessage['uninstallmethod'] = $tmpE->getMessage();
            }
        }
        unset($oPlugin);

        // STEP III: remove Plugin from config Tracker
        $section = $config->Plugins_Tracker;
        if(!empty($section['Plugins_Tracker']) &&  in_array($pluginName, $section['Plugins_Tracker']) ) {
            $config->Plugins_Tracker = array_diff($section['Plugins_Tracker'] , array($pluginName));
        }
        // STEP IV: remove from config installed:
        $section = $config->PluginsInstalled;
        if(!empty($section['PluginsInstalled']) &&  in_array($pluginName, $section['PluginsInstalled']) ) {
            $config->PluginsInstalled = array_diff($section['PluginsInstalled'] , array($pluginName));
        }
        //STEP V: remove from config Plugins
        $section = $config->Plugins;
        if(!empty($section['Plugins']) &&  in_array($pluginName, $section['Plugins']) ) {
            $config->Plugins = array_diff($section['Plugins'] , array($pluginName));
        }

        // STEP V a) bakcup to tmp I: remove dir
        $pwd = DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $pluginName;
        try {
            Piwik_Common::mkdir($backupDir . $pwd );
            Piwik::copyRecursive(PIWIK_INCLUDE_PATH . $pwd, $backupDir . $pwd );
        } catch( Exception $tmpE ) {
            $e = $tmpE;
            $emessage['backup'] = sprintf("backup failed to copy %s\n TO %s\n with %s",
                    PIWIK_INCLUDE_PATH . $pwd,
                    $backupDir . $pwd,
                    $tmpE->getMessage());
        }
        try{
            Piwik::unlinkRecursive(PIWIK_INCLUDE_PATH . $pwd, true);
        } catch( Exception $tmpE ) {
            $error_serious = $tmpE;
            $emessage['unlink'] = sprintf("remove Dir %s failed because of %s ", PIWIK_INCLUDE_PATH . $pwd,  $tmpE->getMessage());
        }
        
        // STEP: delete version option
        try {
            Piwik_Option::getInstance()->delete('version_' . $pluginName);
        } catch( Exception $tmpE ) {
            $e = $tmpE;
            $emessage['deleteoption'] = $tmpE->getMessage();
        }

        // STEP VII: disable maintenance
        $this->disableMaintenance();

        $config->clear();
        if( $error_serious != null ){
            $this->status = self::STATUS_FAILED;
            throw new PluginMarketplace_Installer_Exception('remove Plugin failed:'
                    . implode("\n", $emessage),null, $error_serious);
        }
        $this->status = self::STATUS_REMOVED;
        Piwik::deleteAllCacheOnUpdate();
        
        if($e !== null ) {
            throw new PluginMarketplace_Installer_Exception('remove Plugin succeded but with restrictions:'
                    . implode("\n", $emessage),null, $e);
        }
        return $this;
    }


    /**
     * Disable Maintenance mode , reeinstall tracking
     * @see: Piwik_Updates::disableMaintenance();
     * 
     * we cannot use Piwik_Updates::disableMaintenance(), because is reinititializes Piwik_Config and so it overwrites 
     * our previously changes to the config
     * @TODO: refactor Piwik_Updates to chose "overwrite" or not prev mad config-changes
     * @return PluginMarketplace_InstallerCore
     */
    protected function disableMaintenance()
    {
        $config = Piwik_Config::getInstance();

        $tracker = $config->Tracker;
        $tracker['record_statistics'] = $this->trackerModeOld === null ? 1: $this->trackerModeOld;
        $config->Tracker = $tracker;

        $general = $config->General;
        $general['maintenance_mode']  = $this->generalModeOld === null ? 0 : $this->generalModeOld;
        $config->General = $general;

        $config->forceSave();
        return $this;
    }


    /**
     * Enable MaintenanceMode
     * @see: Piwik_Updates::disableMaintenance();
     * 
     * we cannot use Piwik_Updates::enableMaintenance(), because is reinititializes Piwik_Config and so it overwrites 
     * our previously changes to the config
     * @TODO: refactor Piwik_Updates to chose "overwrite" or not prev mad config-changes
     * // SMELL: config->init() assumed before?
     * @return PluginMarketplace_InstallerCore
     */
    protected function enableMaintenance()
    {
        $config = Piwik_Config::getInstance();

        $tracker = $config->Tracker;
        $this->trackerModeOld = $tracker['record_statistics'];
        $tracker['record_statistics'] = 0;
        $config->Tracker = $tracker;

        $general = $config->General;
        $this->generalModeOld = $general['maintenance_mode'];
        $general['maintenance_mode']  = 1;
        $config->General = $general;

        $config->forceSave();
        return $this;
    }
}


class PluginMarketplace_Installer_Exception extends RuntimeException {}
