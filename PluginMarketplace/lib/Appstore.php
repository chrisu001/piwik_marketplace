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
 * Library: Appstore
 *
 * this class handles all connection (api-calls) to the marketplace:
 * http://plugin.suenkel.org
 * 
 * - get a list of available plugins with its meta-information
 * - register this Instance of Piwik with an unique ID 
 * - get current news (rss)
 *  
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore
{


    /**
     * Label to store the unique ID of this piwik instance as Piwik_Option
     * @var string
     */
    const OPTIONLABEL_UID = 'Appstore_UID';
    /**
     * Namespace of the Zend_Cache
     * to store the available Plugins
     * @var string
     */
    const CACHE_NAMESPACE = 'Appstore';

    const SOCKET_TIMEOUT = 10;

    /**
     * Error Constants to be used in Exceptions
     * @var int
     */
    const ERROR_NOTCONNECTED = 100;
    const ERROR_API          = 101;
    const ERROR_INTERNAL     =  99;

    /**
     * URL of the Appstore
     * @var string
     */
    protected $appstoreUrl='http://plugin.suenkel.org/';

    /**
     * Use release in your request (all, alpha, beta, developer)
     * @var string
     */
    protected $release = 'all';

    /**
     * Flag, if the config is already registered
     * @var boolean
     */
    protected  $isRegistered = false;

    /**
     * UID of this installation
     * @var string
     */
    protected $uid = null;


    /**
     * cache the listed Plugins
     * @var Piwik_CacheFile
     */
    protected $cache = null;

    /**
     * constructor:
     * associate the cache

     * @param string $appurl
     */
    public function __construct($appurl = null)
    {
        if($appurl !== null ) {
            $this->appstoreUrl = $appurl;
        }
        $this->cache = new Piwik_CacheFile(self::CACHE_NAMESPACE);
    }


    /**
     * get the install-ID
     * if no uid exists, generate one and register the uid at appstore
     * the appsotre UID is stored as Option in the piwik_option table

     * @return string - the current userId
     */
    public function getUid()
    {
        if($this->uid !== null) {
            return $this->uid;
        }

        $uid = Piwik_GetOption(self::OPTIONLABEL_UID);
        if($uid == false ) {
            // register this Piwik-instance to the appstore
            $uid = $this->registerMe();
            Piwik_SetOption(self::OPTIONLABEL_UID, $uid);
        }
        $this->uid =  $uid;
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
        if($this->uid !== null) {
            throw new PluginMarketplace_Appstore_Exception('uid is already set while registration:'. $this->uid, self::ERROR_INTERNAL);
        }
        $parameters = array(
                'piwik_version' => Piwik_Version::VERSION,
                'php_version'   => PHP_VERSION,
                'uid'           => md5(uniqid()),
        );

        $response = $this->callApi('register', $parameters);
        if(empty($response['uid'])) {
            throw new PluginMarketplace_Appstore_APIError_Exception('Registration failed',self::ERROR_API);
        }
        return $response['uid'];
    }


    /**
     * Submit all "non-standard"-plugins to the appstore,
     * to calculate dependencies and individual update-information
     * later - reminder per email?
     *
     * @return array - response of the API (success/failed)
     */
    public function registerConfig($listOfPlugins)
    {
        $query = array('config' => $listOfPlugins);
        $this->isRegistered = true;
        return $this->callApi('config', $query);
    }


    /**
     * retreive the configuration data of a dedicated plugin from the pluginstore
     *
     * @param string $webId  - web.identifier or name of a plugin
     *                         if "name" is used, then the info of the latest version will be retreived
     * @throws PluginMarketplace_Appstore_APIError_Exception
     * @return array - config
     */
    public function getPluginInfo($webId)
    {
        $uid = $this->getUid();
        $query = array('webid' => $webId);
        $response = $this->callApi('info', $query);
        if(empty($response['plugin'])){
            throw new PluginMarketplace_Appstore_APIError_Exception('the requested plugin is unknown by the pluginstore:'. $webId);
        }
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
        $uid = $this->getUid();
        
        if(false //FIXME: no cache while development 
        && ($cached = $this->cache->get($uid.$this->release)) 
        && $cached['ttl'] < time() ) {
            return $cached['data'];
        }
        
        $response = $this->callApi('listplugins',array('release' => $this->release));
        if(empty($response['plugins'])) {
            $response['plugins'] = array();
        } else {
            $this->cache->set($uid.$this->release,array('ttl' => time() + 3600, 'data' => $response['plugins']));
        }
        return $response['plugins'];
    }


    /**
     * get the downloadlink of a plugin listed in RemotePlugins
     *
     * @param string $pluginName -  Name or WebId of the Plugin
     * @throws PluginMarketplace_Appstore_APIError_Exception- if plugin was not found in the list
     * @return string - url
     */
    public function getDownloadUrl($pluginName)
    {
        try {
            $plugin = $this->getPluginInfo($pluginName);
        } catch(Exception $e) {
            throw new PluginMarketplace_Appstore_APIError_Exception(Piwik_TranslateException('APUA_Exception_Appstore_nodonwloadlink'), self::ERROR_API, $e);
        }
        return $plugin['download_url'];
    }


    /**
     * Get the news feed of the Appstore
     * @param string $url
     * @return mixed - newsfeed
     */
    public function getRss($url = '/wordpress/?feed=rss2')
    {

        $rssUrl = $this->appstoreUrl. $url;
        if('live' == 'jenkins') {
            $rssUrl = 'http://plugin.suenkel.org' . $url;
        }
        $cached = $this->cache->get('rssfeed'.$url);
        if($cached && $cached['ttl'] < time() ) {
            return $cached['data'];
        }

        // get the Feed
        $retVal = array();
        try {
            $rss = Zend_Feed::import($rssUrl);
        } catch (Exception $e) {
            return array();
        }
        $maxEntries = 4;
        foreach($rss as $post)
        {
            // fix target-href
            $description = preg_replace('#href="#','target="_blank" href="', $post->description());

            $retVal[] = array(
                    'title'       => $post->title(),
                    'date'        => @strftime("%B %e, %Y", strtotime($post->pubDate())),
                    'link'        => $post->link(),
                    'description' => $post->description(),
                    'content'     => $post->content()
            );
            $maxEntries--;
            if($maxEntries <=0){
                break;
            }
        }
        $this->cache->set('rssfeed'.$url, array('ttl'=> time() + 86400 , 'data'=> $retVal));
        return $retVal;
    }

    /*
     * Setter and Getter
    */
    /**
     * Set/select the  release of upcoming query requests to the pluginstore
     *
     * @param string $release - (all, alpha, beta, stable,....)
     * @throws InvalidArgumentException - if unknown release to select
     * @return PluginMarketplace_Appstore
     */
    public function setRelease($release = 'all')
    {
        if(!in_array($release, array('all', 'stable', 'alpha', 'unittest', 'beta', 'developer'))){
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
        $this->appstoreUrl =  $url;
        return $this;
    }


    /**
     * Retreive the current base URL of the appstore
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
     * @param string $method - name of the remote method
     * @param array|null $params - params to be submitted
     * @param string - http method (GET/POST)
     * @throws PluginMarketplace_Appstore_APIError_Exception - if the response yould not be decoded, or an server error occurs
     * @return array - the response
     */
    protected function callApi($method, $params = null, $http_method='GET')
    {
        if($params == null ) {
            $params = array();
        }
        if(!isset($params['uid'])) {
            $params['uid'] = $this->getUid();
        }
        $params['release'] = $this->release;

        $response = $this->rawcallHTTP($method, $params, $http_method);
        if(!isset($response['error']) || $response['error'] !== false ) {
            throw new PluginMarketplace_Appstore_APIError_Exception(
                    sprintf('the Pluginstore does not understand the request:"%s" Response: "%s"'
                            , print_r($params, true)
                            , print_r($response, true))
                    , self::ERROR_API);
        }
        return $response;
    }


    /**
     * invoke remote api-call to the appstore
     *
     * @param string $method - name of the remote method
     * @param array|null $params - params to be submitted
     * @param string - http method (GET/POST)
     * @throws PluginMarketplace_Appstore_Connection_Exception - if a connection or server error occurs
     * @throws InvalidArgumentException - POST-mehtod not implemented yet by Piwik_Http
     * @return array - the response
     */
    protected function rawcallHTTP($method,array  $params, $httpmethod='GET')
    {
        $url = $this->appstoreUrl.'ajax/api/'.$method.'?'.http_build_query($params);

        try {
            if($httpmethod == 'POST') {
                throw new InvalidArgumentException('Post not implemented yet :(');
            } else {
                $remoteResult = Piwik_Http::sendHttpRequest($url, 10);
            }
        } catch(Exception $e) {
            // e.g., disable_functions = fsockopen; allow_url_open = Off
            throw new PluginMarketplace_Appstore_Connection_Exception('cannot connect to Pluginstore', self::ERROR_NOTCONNECTED, $e);
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
class PluginMarketplace_Appstore_Exception extends RuntimeException {};

/**
 * Exception
 *
 * thrown, if an API-Error occurs
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore_APIError_Exception extends BadMethodCallException {};

/**
 * Exception
 *
 * thrown, if the HTTP-connection or the server is not available
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Appstore_Connection_Exception extends RuntimeException {};
