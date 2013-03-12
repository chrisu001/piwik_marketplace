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
 * This plugins provides a conection to the Plugin-Store http://plugin.suenkel.org
 * - remote update of Piwik-Plugins
 * - new tab Settings->Pluginstore
 * - upload own zip-filed plugins
 *  *
 * @package Piwik_PluginMarketplace
 */
class Piwik_PluginMarketplace extends Piwik_Plugin
{

    /**
     * Standardinformation about this plugin
     * (non-PHPdoc)
     * @see Piwik_Plugin::getInformation()
     */
    public function getInformation()
    {
        return array(
                'description'          => Piwik_Translate('APUA_PluginDescription'),
                'author'               => 'Christian Suenkel',
                'author_homepage'      => 'http://www.suenkel.de/',
                'homepage'             => 'http://plugin.suenkel.org/',
                'version'              => '0.9',
                'translationAvailable' => true,
        );
    }


    /**
     * Register the callback-hooks, where the plugin wants to be called
     * - add a tab in the settings-menue
     * - register additional css and js files
     * - hijack the Plugin-Managers index page (disabled atm)
     * @see Piwik_Plugin::getListHooksRegistered()
     */
    public function getListHooksRegistered()
    {
        return array(
                'AdminMenu.add'            => 'addMenu',
                'AssetManager.getCssFiles' => 'getCssFiles',
                'WidgetsList.add'          => 'addWidgets',
                // 'AssetManager.getJsFiles'  => 'getJsFiles',
                // 'FrontController.dispatch' => 'forceNewPlugin',
        );
    }


    /**
     * Add Admin-Menu "Appstore"
     */
    public function addMenu()
    {
        if( !function_exists('Piwik_AddAdminSubMenu')) {
            Piwik_AddAdminMenu('APUA_AdminMenu', array('module' => 'PluginMarketplace', 'action' => 'index'),Piwik::isUserIsSuperUser(), 8);
        } else {
            Piwik_AddAdminSubMenu('CorePluginsAdmin_MenuPlugins', 'APUA_AdminMenu',
            array('module' => 'PluginMarketplace', 'action' => 'index'),
            Piwik::isUserIsSuperUser(),
            $order = 1);
        }
    }


    /**
     * Add the RSS-Feed Widget
     */
    public function addWidgets() {
        // first param, catgeroy, untranslated....
        // second param is locakey....
        Piwik_AddWidget('Marketplace', 'APUA_Feed_Widget_Title', 'PluginMarketplace', 'rss');
    }

    /**
     * Overwrite the "standard" PluginManager - index page
     * Hijack the Plugin-Managers- Index page
     * at the moment this is not enenabled
     * TODO: configure this by a switch in the "advanced settings-tab"
     * @param Piwik_Event_Notification $notification - notification object with an array of module/method/params
     */
    public function forceNewPlugin (Piwik_Event_Notification &$notification)
    {
        // $params = array($controller, $action, $parameters);
        // Piwik_PostEvent('FrontController.dispatch', $params);
        $dispatcher = &$notification->getNotificationObject();
        if($dispatcher[0] == 'CorePluginsAdmin' && $dispatcher[1]=='index' ) {
            $dispatcher[0] = 'PluginMarketplace';
        }
    }


    /**
     * Additional css-assets
     * @param Piwik_Event_Notification $notification - notification object
     */
    public function getCssFiles( $notification )
    {
        $cssFiles = &$notification->getNotificationObject();
        $cssFiles[] = 'plugins/PluginMarketplace/templates/styles.css';
    }


}