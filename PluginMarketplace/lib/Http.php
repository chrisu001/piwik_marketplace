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
 * Library: Http
 *
 * extend the Piwik-Http to do HTTP-Requests vie "POST"
 *
 * @package Piwik_PluginMarketplace
 * @subpackage lib
 */
class PluginMarketplace_Http extends Piwik_Http
{
    /**
     * Sends http request via POST using the specified transport method
     *
     * @param string       $method
     * @param string       $aUrl
     * @param int          $timeout
     * @param string       $userAgent
     * @param string       $destinationPath
     * @param resource     $file
     * @param int          $followDepth
     * @param bool|string  $acceptLanguage               Accept-language header
     * @param bool         $acceptInvalidSslCertificate  Only used with $method == 'curl'. If set to true (NOT recommended!) the SSL certificate will not be checked
     * @throws Exception
     * @return bool  true (or string) on success; false on HTTP response error code (1xx or 4xx)
     */
    static public function postHttpRequest($aUrl, $destinationPath = null)
    {
        $fileLength = 0;
        $file = null;
        $timeout = 10; 

        $url = parse_url($aUrl);
        
        if($destinationPath !== null ) {
            $file = fopen ($destinationPath, 'wb');
        }

        // Piwik services behave like a proxy, so we should act like one.
        $xff = 'X-Forwarded-For: '
                . (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] . ',' : '')
                . Piwik_IP::getIpFromHeader();

        $userAgent = self::getUserAgent();
         

        $via = 'Via: '
                . (isset($_SERVER['HTTP_VIA']) && !empty($_SERVER['HTTP_VIA']) ? $_SERVER['HTTP_VIA'] . ', ' : '')
                . Piwik_Version::VERSION . ' '
                        . ($userAgent ? " ($userAgent)" : '');

        // proxy configuration
        $proxyHost = Piwik_Config::getInstance()->proxy['host'];
        $proxyPort = Piwik_Config::getInstance()->proxy['port'];
        $proxyUser = Piwik_Config::getInstance()->proxy['username'];
        $proxyPassword = Piwik_Config::getInstance()->proxy['password'];

        $response = false;

        // we make sure the request takes less than a few seconds to fail
        // we create a stream_context (works in php >= 5.2.1)
        // we also set the socket_timeout (for php < 5.2.1)
        $default_socket_timeout = @ini_get('default_socket_timeout');
        @ini_set('default_socket_timeout', $timeout);

        $query= empty($url['query'])?'empty': $url['query'];
        $ctx = null;
        $stream_options = array(
                'http' => array(
                        'header' => 'User-Agent: '.$userAgent."\r\n"
                        .$xff."\r\n"
                        .$via."\r\n"
                        .'Content-type: application/x-www-form-urlencoded',
                        'max_redirects' => 5, // PHP 5.1.0
                        'timeout' => $timeout, // PHP 5.2.1
                        'method' => 'POST',
                        'content' => $query,
                )
        );

        if(!empty($proxyHost) && !empty($proxyPort))
        {
            $stream_options['http']['proxy'] = 'tcp://'.$proxyHost.':'.$proxyPort;
            $stream_options['http']['request_fulluri'] = true; // required by squid proxy
            if(!empty($proxyUser) && !empty($proxyPassword))
            {
                $stream_options['http']['header'] .= 'Proxy-Authorization: Basic '.base64_encode("$proxyUser:$proxyPassword")."\r\n";
            }
        }

        $ctx = stream_context_create($stream_options);
        $postUrl=sprintf('%s://%s:%d%s',$url['scheme'],$url['host'],(!empty($url['port'])? $url['port']:80) ,$url['path']);
        
        // defined ('IS_PHPUNIT') && printf("%S: %s, Query: %s\n",__METHOD__,$postUrl, $query);
        // save to file
        if(is_resource($file))
        {
            $handle = fopen($postUrl, 'rb', false, $ctx);
            while(!feof($handle))
            {
                $response = fread($handle, 8192);
                $fileLength += Piwik_Common::strlen($response);
                fwrite($file, $response);
            }
            fclose($handle);
            fclose($file);
        } else {
            $response = @file_get_contents($postUrl, 0, $ctx);
            $fileLength = Piwik_Common::strlen($response);
        }

        // restore the socket_timeout value
        if(!empty($default_socket_timeout))
        {
            @ini_set('default_socket_timeout', $default_socket_timeout);
        }
        return trim($response);
    }


    /**
     * Fetch the file at $url in the destination $destinationPath
     *
     * @param string  $url
     * @param string  $destinationPath
     * @param int     $tries
     * @throws Exception
     * @return bool  true on success, throws Exception on failure
     */
    static public function fetchRemoteFile($url, $destinationPath = null, $tries = 0)
    {
        @ignore_user_abort(true);
        Piwik::setMaxExecutionTime(0);
        return self::postHttpRequest($url, 10, 'Update', $destinationPath, $tries);
    }
}
