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
require_once dirname(__FILE__) . '/Processor.php';
require_once dirname(__FILE__) . '/../Cache.php';
/**
 * Create a processor (new or from cache)
 * or store a processor to the cache
 *
 * @package PluginMarketplace
 */
class Marketplace_ProcessorFactory
{
    const CACHE_ID_PROC = 'processor';

    /**
     * create new Processor
     *
     * @return Marketplace_Processor
     */
    public static function newInstance()
    {
        return new Marketplace_Processor();
    }

    /**
     * setup a processor from cache
     *
     * @param PluginMarketplace_Cache $cache
     *            - optional cache
     * @param string $cacheKey
     *            - optional
     * @return NULL or Marketplace_Processor, if cached processor available
     */
    public static function fromCache(PluginMarketplace_Cache $cache = null, $cacheKey = null)
    {
        if ($cache == null) {
            $cache = PluginMarketplace_Cache::getInstance();
        }
        $cacheKey = ($cacheKey == null) ? self::CACHE_ID_PROC : $cacheKey;
        $proc = $cache->get($cacheKey);
        if (!($proc instanceof Marketplace_Processor)) {
            return null;
        }
        return $proc;
    }

    /**
     *
     * @param Marketplace_Processor $proc
     *            - optional processor
     * @param PluginMarketplace_Cache $cache
     *            - optional cache-instance
     * @param string $cacheKey
     *            - optional
     */
    public static function toCache(Marketplace_Processor $proc = null, PluginMarketplace_Cache $cache = null, $cacheKey = null)
    {
        if ($cache == null) {
            $cache = PluginMarketplace_Cache::getInstance();
        }
        $cacheKey = ($cacheKey == null) ? self::CACHE_ID_PROC : $cacheKey;
        $cache->set($cacheKey, $proc);
    }
}