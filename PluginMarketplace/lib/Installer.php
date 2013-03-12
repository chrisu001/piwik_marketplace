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

require_once __DIR__ . DIRECTORY_SEPARATOR .'InstallerCore.php';

/**
 * Library: Installer
 *
 *
 * this class handles
 * - deployment (copy) a plugin from its tmp(download) path to the piwik instance
 * - basic verify the plugin
 *  derived form Installer Core as Facade to Piwik_PluginsManager
 * - activate/deactivate
 * - @TODO: forceinstall - install with database updates
 * - remove Plugin (experimental)
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Installer extends PluginMarketplace_InstallerCore
{

    /**
     * Status constants as result of the install process
     * @var int
     */
    CONST STATUS_VERIFIED    =  1;
    CONST STATUS_DEPLOYED    =  2;
    CONST STATUS_INSTALLED   =  3;
     
    /**
     * get the current Pluginname
     * tries to catch the real-pluginname from the source
     * @throws PluginMarketplace_Installer_Exception - if the extraction of the Pluginname fails
     * @return string
     */
    public function getPluginName()
    {
        if($this->pluginName != null){
            return $this->pluginName;
        }
        return $this->fetchPluginnameFromSource();
    }


    /**
     * try to fetch the the name of the plugin from the plugin located in $workspace
     * @throws PluginMarketplace_Installer_Exception - if its not possible to calculate the Plugininame
     * @return string - pluginname
     */
    protected function fetchPluginnameFromSource()
    {
        $this->fallbackRelocatePluginDir();
        $workspace = $this->getWorkspace();
        // check "/plugins/"  directory existances and uniqness
        $content = glob($workspace .'*');
        if(count($content) !=1 || basename($content[0]) != 'plugins' || !is_dir($content[0])) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_noplugdir', htmlentities(print_r($content,true))));
        }
        // check plugin-name exists and is unique
        $content = glob($workspace . 'plugins' . DIRECTORY_SEPARATOR . '*');
        if( count($content) !=1 || !is_dir($content[0])) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_noplugname', htmlentities(print_r($content,true))));
        }
        $this->pluginName = basename($content[0]);
        return $this->pluginName;
    }

    /**
     * this function tries to relocate/arrange the plugindir, if it does not fit the specification
     * so it renames
     * "$workspace/$pluginName" to "$workspace/plugins/$pluginName"
     * if neccesary
     */
    protected function fallbackRelocatePluginDir()
    {
        $workspace = $this->getWorkspace();
        // check "/plugins/" dir existances and uniqness
        $content = glob($workspace .'*');
        if(count($content) == 1 &&  basename($content[0]) != 'plugins') {
            Piwik_Common::mkdir($workspace . DIRECTORY_SEPARATOR . 'plugins');
            rename($workspace . DIRECTORY_SEPARATOR . basename($content[0]),
            $workspace . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . basename($content[0]));
        }
    }


    /*
     * process steps
    * - verify
    * - deploy
    * - install
    */
    /**
     * Simple test , if the plguin-source is valid
     * @throws PluginMarketplace_Installer_Exception - if not is valid
     * @return PluginMarketplace_Installer
     */
    public function verify()
    {
        $phpclassfilename = $this->getPluginPHPClassFilename();
        if(function_exists('php_check_syntax') && !php_check_syntax( $phpclassfilename )) {
            $this->status = self::STATUS_FAILED;
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_noplugfile',htmlentities($phpclassfilename)));
        }
        $this->status = self::STATUS_VERIFIED;
        return $this;
    }


    /**
     * deploy the plugin
     * copy the plugin-dir to Piwik
     * 
     * @throws PluginMarketplace_Installer_Exception - if status was "failed"
     * @return PluginMarketplace_Installer
     */
    public function deploy()
    {
        if($this->status <= self::STATUS_FAILED) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal',''));
        }
        if($this->status < self::STATUS_VERIFIED) {
            $this->verify();
        }
        Piwik::copyRecursive($this->getWorkspace(), PIWIK_INCLUDE_PATH);
        if(function_exists('apc_clear_cache'))
        {
            apc_clear_cache(); // clear the system (aka 'opcode') cache
        }
        $this->status = self::STATUS_DEPLOYED;
        return $this;
    }


    /**
     * install the plugin
     * register the plugin to piwik
     * @throws PluginMarketplace_Installer_Exception - if status was "failed"
     * @return PluginMarketplace_Installer
     */
    public function install()
    {
        if($this->status <= self::STATUS_FAILED) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal',''));
        }
        if($this->status < self::STATUS_DEPLOYED) {
            $this->deploy();
        }
        // at the moment, we have nothing to do, because of "autoloaders"
        $this->postverify();
        $this->status = self::STATUS_INSTALLED;
        return $this;
    }


    /**
     * do basic verifications after the deploy process
     * @throws PluginMarketplace_Installer_Exception - if fails
     * @return PluginMarketplace_Installer
     */
    public function postverify() {

        if($this->status <= self::STATUS_FAILED) {
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('APUA_Exception_Install_internal', ''));
        }
        // check file existance
        $targetDir= PIWIK_INCLUDE_PATH . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $this->getPluginName() . DIRECTORY_SEPARATOR;
        if(!is_dir($targetDir)) {
            $this->status = self::STATUS_FAILED;
            throw new PluginMarketplace_Installer_Exception(Piwik_TranslateException('PluginMarketplace_ExceptionInstall_nopostplugdir',htmlentities($targetDir)));
        }
        return $this;
    }
}
