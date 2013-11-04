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
require_once dirname(__FILE__) . '/Cache.php';

/**
 * Library: Appstore
 *
 * this class handles all connection (api-calls) to the alternative marketplace:
 * http://plugin.suenkel.org
 *
 * - get a list of available plugins with its meta-information
 * - register this Instance of Piwik with an unique ID
 * - get current news (rss)
 *
 * @package PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore
{
    /**
     * Label to store the unique ID of this piwik instance as Piwik_Option
     *
     * @var string
     */
    const OPTIONLABEL_UID = 'Appstore_UID';
    
    /**
     * Error Constants to be used in Exceptions
     *
     * @var int
     */
    const ERROR_NOTCONNECTED = 100;
    const ERROR_API = 101;
    const ERROR_INTERNAL = 99;
    
    /**
     * URL of the Appstore
     *
     * @var string
     */
    protected $appstoreUrl = 'http://plugin.suenkel.org/';
    
    /**
     * Use release in your request (all, alpha, beta, developer)
     *
     * @var string
     */
    protected $release = 'all';
    
    /**
     * Flag, if the config is already registered
     *
     * @var boolean
     */
    protected $isRegistered = false;
    
    /**
     * UID of this installation
     *
     * @var string
     */
    protected $uid = null;
    
    /**
     * cache the listed Plugins
     *
     * @var PluginMarketplace_Cache
     */
    protected $cache = null;

    /**
     * constructor:
     * associate the cache
     *
     * @param string $appurl            
     */
    public function __construct($appurl = null)
    {
        if ($appurl !== null) {
            $this->appstoreUrl = $appurl;
        }
        $this->cache = PluginMarketplace_Cache::getInstance();
    }

    /**
     * get the install-ID
     * if no uid exists, generate one and register the uid at plugin.suenkel.org
     * the appstore UID is stored as Option in the piwik_option table
     *
     * @return string - the current userId
     */
    public function getUid()
    {
        if ($this->uid !== null) {
            return $this->uid;
        }
        
        $uid = Piwik_GetOption(self::OPTIONLABEL_UID);
        if ($uid == false) {
            // register this Piwik-instance to the appstore
            $uid = $this->registerMe();
            Piwik_SetOption(self::OPTIONLABEL_UID, $uid);
        }
        $this->uid = $uid;
        return $this->uid;
    }

    /**
     * Register this instance of Piwik to the Appstore
     *
     * @throws PluginMarketplace_Appstore_APIError_Exception - if registration failed
     * @return string - the generated userID
     */
    protected function registerMe()
    {
        if ($this->uid !== null) {
            throw new PluginMarketplace_Appstore_Exception(
                    'uid is already set while registration:' . $this->uid, self::ERROR_INTERNAL);
        }
        $parameters = array('piwik_version' => Piwik_Version::VERSION, 
                'php_version' => PHP_VERSION, 
                'uid' => md5(uniqid()));
        
        $response = $this->callApi('register', $parameters);
        if (empty($response['uid'])) {
            throw new PluginMarketplace_Appstore_APIError_Exception('Registration failed', 
                    self::ERROR_API);
        }
        return $response['uid'];
    }

    /**
     * retreive the configuration data of a  plugin from the pluginstore
     *
     * @param string $webId
     *            - web.identifier or name of a plugin
     *            if "name" is used, then the info of the latest version will be retreived
     * @throws PluginMarketplace_Appstore_APIError_Exception
     * @return array - config
     */
    public function getPluginInfo($webId)
    {
        if ($cached = $this->cache->get('appstore_info' . $webId)) {
            return $cached;
        }
        $uid = $this->getUid();
        $query = array('webid' => $webId);
        $response = $this->callApi('info', $query);
        if (empty($response['plugin'])) {
            throw new PluginMarketplace_Appstore_APIError_Exception(
                    'the requested plugin is unknown by the pluginstore:' . $webId);
        }
        $this->cache->set('appstore_info' . $webId, $response['plugin']);
        return $response['plugin'];
    }

    /**
     * List the remoteplugins (with latest versions in release $this->release)
     * also handle the response, that the opluginstore wants to know your installed
     * plugins to provide additional information on the website
     *
     * @throws PluginMarketplace_Appstore_Exception
     * @return array - hash of available plugins
     */
    public function listPlugins()
    {
        if ($cached = $this->cache->get('appstore_list' . $this->release)) {
            return $cached;
        }
        
        $uid = $this->getUid();
        $response = $this->callApi('listplugins', array('release' => $this->release));
        if (empty($response['plugins'])) {
            $response['plugins'] = array();
        }
        
        $this->cache->set('appstore_list' . $this->release, $response['plugins']);
        return $response['plugins'];
    }

    /**
     * get the downloadlink of a plugin listed in RemotePlugins
     *
     * @param string $pluginName
     *            - Name or WebId of the Plugin
     * @throws PluginMarketplace_Appstore_APIError_Exception- if plugin was not found in the list
     * @return string - url
     */
    public function getDownloadUrl($pluginName)
    {
        try {
            $plugin = $this->getPluginInfo($pluginName);
        } catch (Exception $e) {
            throw new PluginMarketplace_Appstore_APIError_Exception(
                    Piwik_TranslateException('APUA_Exception_Appstore_nodonwloadlink'), 
                    self::ERROR_API, $e);
        }
        return $plugin['download_url'];
    }

    /**
     * Get the news feed of the Appstore
     *
     * @param string $url            
     * @return mixed - newsfeed
     */
    public function getRss($url = '/wordpress/?feed=rss2')
    {
        $rssUrl = $this->appstoreUrl . $url;
        if ('live' == 'jenkins') {
            // use real URL while Development
            $rssUrl = 'http://plugin.suenkel.org' . $url;
        }
        if ($cached = $this->cache->get('rssfeed' . $rssUrl)) {
            return $cached;
        }
        
        // get the Feed
        $retVal = array();
        try {
            $rss = Zend_Feed::import($rssUrl);
        } catch (Exception $e) {
            return array();
        }
        $maxEntries = 4;
        foreach ($rss as $post) {
            // fix target-href
            $description = preg_replace('#href="#', 'target="_blank" href="', $post->description());
            
            $retVal[] = array('title' => $post->title(), 
                    'date' => @strftime("%B %e, %Y", strtotime($post->pubDate())), 
                    'link' => $post->link(), 
                    'description' => $post->description(), 
                    'content' => $post->content());
            $maxEntries--;
            if ($maxEntries <= 0) {
                break;
            }
        }
        $this->cache->set('rssfeed' . $rssUrl, $retVal);
        return $retVal;
    }
    
    /*
     * Setter and Getter
     */
    /**
     * Set/select the release of upcoming query requests to the pluginstore
     *
     * @param string $release
     *            - (all, alpha, beta, stable,....)
     * @throws InvalidArgumentException - if unknown release to select
     * @return PluginMarketplace_Appstore
     */
    public function setRelease($release = 'all')
    {
        if (!in_array($release, array('all', 'stable', 'alpha', 'unittest', 'beta', 'developer'))) {
            throw new InvalidArgumentException('unknow release');
        }
        $this->release = $release;
        return $this;
    }

    /**
     * Set the base URL of the appstore API
     *
     * @param string $url            
     * @return PluginMarketplace_Appstore
     */
    public function setAppstoreUrl($url = 'http://plugin.suenkel.org/')
    {
        $this->appstoreUrl = $url;
        return $this;
    }

    /**
     * Retreive the current base URL of the appstore
     *
     * @return string
     */
    public function getAppstoreUrl()
    {
        return $this->appstoreUrl;
    }
    
    /*
     * Api calls to the plugstore
     */
    
    /**
     * invoke remote api-call to the appstore and auto add the UID
     *
     * @param string $method
     *            - name of the remote method
     * @param array|null $params
     *            - params to be submitted
     * @param
     *            string - http method (GET/POST)
     * @throws PluginMarketplace_Appstore_APIError_Exception - if the response yould not be decoded, or an server error occurs
     * @return array - the response
     */
    protected function callApi($method, $params = null, $http_method = 'GET')
    {
        if ($params == null) {
            $params = array();
        }
        if (!isset($params['uid'])) {
            $params['uid'] = $this->getUid();
        }
        $params['release'] = $this->release;
        
        $response = $this->rawcallHTTP($method, $params, $http_method);
        if (!isset($response['error']) || $response['error'] !== false) {
            throw new PluginMarketplace_Appstore_APIError_Exception(
                    sprintf('the Pluginstore does not understand the request:"%s" Response: "%s"', 
                            print_r($params, true), print_r($response, true)), self::ERROR_API);
        }
        return $response;
    }

    /**
     * invoke remote api-call to the appstore
     *
     * @param string $method
     *            - name of the remote method
     * @param array|null $params
     *            - params to be submitted
     * @param
     *            string - http method (GET/POST)
     * @throws PluginMarketplace_Appstore_Connection_Exception - if a connection or server error occurs
     * @throws InvalidArgumentException - POST-mehtod not implemented yet by Piwik_Http
     * @return array - the response
     */
    protected function rawcallHTTP($method, array $params, $httpmethod = 'GET')
    {
        $url = $this->appstoreUrl . 'ajax/api/' . $method . '?' . http_build_query($params);
        
        try {
            if ($httpmethod == 'POST') {
                throw new InvalidArgumentException('Post not implemented yet :(');
            } else {
                $remoteResult = Piwik_Http::sendHttpRequest($url, 10);
            }
        } catch (Exception $e) {
            // e.g., disable_functions = fsockopen; allow_url_open = Off
            throw new PluginMarketplace_Appstore_Connection_Exception(
                    'cannot connect to Pluginstore', self::ERROR_NOTCONNECTED, $e);
        }
        return json_decode($remoteResult, true);
    }
}

/**
 * Exception
 *
 * thrown, if a general error occurs
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore_Exception extends RuntimeException
{}

/**
 * Exception
 *
 * thrown, if an API-Error occurs
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore_APIError_Exception extends BadMethodCallException
{}

/**
 * Exception
 *
 * thrown, if the HTTP-connection or the server is not available
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore_Connection_Exception extends RuntimeException
{}
