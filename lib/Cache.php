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

/**
 * Library: Cache
 *
 * extends the normal Piwik_CacheFile with the ability to set a TTL and to store objects/arrays
 *
 * @package PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Cache extends Piwik_CacheFile
{
    
    /**
     * time to live in seconds, default 3600 sec= 1h
     *
     * @var int
     */
    protected $cache_ttl = 3600;
    const CACHE_NAMESPACE = 'PluginMarketplace';
    /**
     * Singleton
     *
     * @var PluginMarketplace_Cache
     */
    protected static $instance = null;

    /**
     * create std instance
     *
     * @return PluginMarketplace_Cache
     */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self(self::CACHE_NAMESPACE);
        }
        return self::$instance;
    }

    /**
     * set the TTL
     *
     * @param int $seconds            
     * @return PluginMarketplace_Cache
     */
    public function setCacheTTL($seconds)
    {
        $this->cache_ttl = $seconds;
        return $this;
    }

    /**
     * fetch a cache entry
     * caution, if unavailable it returns "false" to be compatible with Piwik_Cachefile
     *
     * @param string $id
     *            The cache entry ID
     * @return mixed bool on error, or array the cache content
     */
    public function get($id)
    {
        $filename = $this->cachePath . urlencode($id);
        if (!file_exists($filename)) {
            return false;
        }
        $retryCounter = 10;
        while (!($var = file_get_contents($filename)) && $retryCounter > 0) {
            usleep(200);
            $retryCounter--;
        }
        if (!$var) {
            return false;
        }
        $var = unserialize($var);
        if ($var['ttl'] < time()) {
            return false;
        }
        return $var['payload'];
    }

    /**
     * Set the value of a cache-key
     * implicit with $cache_ttl
     * caution: returns void NOT PluginMarketplace_Cache to be compatible with Piwik_Cache
     *
     * @see Piwik_CacheFile::set()
     * @throws RuntimeException - if it is not impossible to store the cachefile
     * @return void
     */
    public function set($id, $content)
    {
        if (!is_dir($this->cachePath)) {
            if (file_exists($this->cachePath)) {
                throw new RuntimeException(
                        Piwik_TranslateException('APUA_Exception_Cache_notexists'));
            }
            Piwik_Common::mkdir($this->cachePath);
        }
        if (!is_writable($this->cachePath)) {
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Cache_notwriteable'));
        }
        
        $filename = $this->cachePath . urlencode($id);
        if (!file_put_contents($filename, 
                serialize(array('ttl' => time() + $this->cache_ttl, 'payload' => $content)))) {
            throw new RuntimeException(Piwik_TranslateException('APUA_Exception_Cache_unwriteable'));
        }
    }
}