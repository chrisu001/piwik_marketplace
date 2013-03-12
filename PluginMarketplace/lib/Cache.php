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
 * Library: Cache
 *
 * extends the normal Piwik_CacheFile with the ability to set a TTL and to store objects/arrays
 * @package  Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Cache extends Piwik_CacheFile
{

    /**
     * time to live in seconds, default 3600 sec= 1h
     * @var int
     */
    protected $cache_ttl = 3600;


    /**
     * set the TTL
     * @param int $seconds
     * @return PluginMarketplace_Cache
     */
    public function setCacheTTL($seconds) {
        $this->cache_ttl = $seconds;
        return $this;
    }


    /**
     * fetch a cache entry
     * caution, if unavailable it returns "false" to be compatible with Piwik_Cache
     *
     * @param string  $id  The cache entry ID
     * @return mixed|bool  False on error, or array the cache content
     */
    public function get($id)
    {
        $filename = $this->cachePath . urlencode($id);
        if(!file_exists($filename)){
            return false;
        }
        $retryCounter = 10 ;
        while (!($var = file_get_contents($filename)) && $retryCounter>0){
            usleep(100);
            $retryCounter--;
        }
        if(!$var){
            return false;
        }
        $var = unserialize($var);
        if($var['ttl']  < time()) {
            return false;
        }
        return $var['payload'];
    }

    /**
     * Set the value of a cache-key
     * implicit with $cache_ttl
     * caution: returns void NOT PluginMarketplace_Cache to be compatible with Piwik_Cache
     * @see Piwik_CacheFile::set()
     * @throws RuntimeException - if it is not impossible to store the cachefile
     * @return void
     */
    public function set($id, $content)
    {
        if(!is_dir($this->cachePath))
        {
            if(file_exists($this->cachePath))
            {
                throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Cache_notexists'));
            }
            Piwik_Common::mkdir($this->cachePath);
        }
        if (!is_writable($this->cachePath))
        {
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Cache_notwriteable'));
        }

        $filename = $this->cachePath . urlencode($id);
        if(!file_put_contents($filename, serialize(
                array(
                        'ttl'     => time() +  $this->cache_ttl,
                        'payload' => $content))))
        {
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Cache_unwriteable'));
        }
    }
}